#!/usr/bin/env python3
"""Prove the full QcMigration readiness contract matches all three SQL files."""

from __future__ import annotations

import json
import pathlib
import re
import subprocess


ROOT = pathlib.Path(__file__).resolve().parents[2]
php = "require '" + str(ROOT / "custom/chinaplanetoperations/class/QcMigration.class.php") + "'; echo json_encode(QcMigration::schemaContract());"
contract = json.loads(subprocess.check_output(["php", "-r", php], text=True))


def fail(reason: str) -> None:
    raise SystemExit("M074_SCHEMA_CONTRACT=FAIL REASON=" + reason)


def normalize_default(raw: str | None):
    if raw is None or raw.upper() == "NULL":
        return None
    if len(raw) >= 2 and raw[0] == raw[-1] == "'":
        return raw[1:-1].replace("''", "'")
    if raw.upper() == "CURRENT_TIMESTAMP":
        return "current_timestamp"
    return raw


def normalize_type(raw: str) -> str:
    value = raw.lower()
    return "int" if value == "integer" else value


for table, expected in contract.items():
    sql_path = ROOT / "custom/chinaplanetoperations/sql" / ("llx_" + table + ".sql")
    lines = sql_path.read_text(encoding="utf-8").splitlines()[1:-1]
    columns = {}
    indexes = {}
    for raw_line in lines:
        line = raw_line.strip().rstrip(",")
        primary_inline = re.fullmatch(r"([a-z0-9_]+)\s+([^ ]+)\s+AUTO_INCREMENT\s+PRIMARY KEY", line, re.I)
        if primary_inline:
            name, column_type = primary_inline.groups()
            columns[name] = [normalize_type(column_type), "NO", None, "auto_increment"]
            indexes["PRIMARY"] = [0, [name], "BTREE", [None]]
            continue
        index = re.fullmatch(r"(UNIQUE\s+)?KEY\s+([a-z0-9_]+)\s*\(([^)]+)\)", line, re.I)
        if index:
            unique, name, raw_columns = index.groups()
            index_columns = [value.strip().strip("`") for value in raw_columns.split(",")]
            indexes[name] = [0 if unique else 1, index_columns, "BTREE", [None] * len(index_columns)]
            continue
        column = re.fullmatch(
            r"([a-z0-9_]+)\s+([^ ]+)(?:\s+(NOT NULL|NULL))?(?:\s+DEFAULT\s+('(?:''|[^'])*'|CURRENT_TIMESTAMP|NULL|-?[0-9]+))?(?:\s+ON UPDATE\s+CURRENT_TIMESTAMP)?",
            line,
            re.I,
        )
        if not column:
            fail("UNPARSED_SQL_" + table + "_" + line)
        name, column_type, nullable, default = column.groups()
        extra = "on update current_timestamp" if re.search(r"ON UPDATE CURRENT_TIMESTAMP$", line, re.I) else ""
        columns[name] = [normalize_type(column_type), "YES" if (nullable or "").upper() == "NULL" else "NO", normalize_default(default), extra]
    if columns != expected["columns"]:
        differing = next((name for name in set(columns) | set(expected["columns"]) if columns.get(name) != expected["columns"].get(name)), "unknown")
        fail("COLUMN_MISMATCH_" + table + "_" + differing + "_ACTUAL=" + repr(columns.get(differing)) + "_EXPECTED=" + repr(expected["columns"].get(differing)))
    if indexes != expected["indexes"]:
        fail("INDEX_MISMATCH_" + table)

print("M074_SCHEMA_CONTRACT=PASS TABLES=3 COLUMNS=95 INDEXES=16")
