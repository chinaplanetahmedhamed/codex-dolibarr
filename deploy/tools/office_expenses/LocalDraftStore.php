<?php
declare(strict_types=1);
require_once __DIR__.'/../../../custom/chinaplanetoperations/office_expenses/class/OfficeExpenseDraftService.php';

/** Synthetic local workbench adapter ONLY. Never connect this to a native ERP database. */
final class LocalDraftStore implements OfficeExpenseDraftStore
{
    private $pdo;
    public function __construct(PDO $pdo)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') throw new InvalidArgumentException('Local synthetic workbench requires SQLite.');
        $this->pdo=$pdo;
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS oe_draft (id TEXT PRIMARY KEY, entity INTEGER NOT NULL, owner_id INTEGER NOT NULL, submission_key TEXT NOT NULL, payload_hash TEXT NOT NULL, document_json TEXT NOT NULL, UNIQUE(entity,owner_id,submission_key))');
        $pdo->exec('CREATE TABLE IF NOT EXISTS oe_audit (id INTEGER PRIMARY KEY, draft_id TEXT NOT NULL, entity INTEGER NOT NULL, owner_id INTEGER NOT NULL, event TEXT NOT NULL, created_at TEXT NOT NULL)');
    }

    public function save(int $entity, int $owner, string $key, array $draft): array
    {
        if (($draft['entity'] ?? null) !== $entity || ($draft['owner_id'] ?? null) !== $owner || ($draft['status'] ?? null) !== 'draft' || !array_key_exists('native_record', $draft) || $draft['native_record'] !== null) {
            throw new InvalidArgumentException('Invalid draft context or posting state.');
        }
        if ($this->pdo->inTransaction()) throw new LogicException('Local draft store owns its transaction; nested transactions are not supported.');
        $hash=hash('sha256', json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $existing=$this->existing($entity, $owner, $key, $hash);
        if ($existing !== null) return $existing;
        $document=$draft;
        $document['id']=bin2hex(random_bytes(16));
        $document['created_at']=gmdate('Y-m-d\TH:i:s\Z');
        $this->pdo->beginTransaction();
        try {
            $insert=$this->pdo->prepare('INSERT INTO oe_draft (id,entity,owner_id,submission_key,payload_hash,document_json) VALUES (?,?,?,?,?,?)');
            $insert->execute(array($document['id'],$entity,$owner,$key,$hash,json_encode($document, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)));
            $audit=$this->pdo->prepare('INSERT INTO oe_audit (draft_id,entity,owner_id,event,created_at) VALUES (?,?,?,?,?)');
            $audit->execute(array($document['id'],$entity,$owner,'draft_created',$document['created_at']));
            $this->pdo->commit();
            return $document;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            // A competing unique-key insert may have won; accept only the identical intent.
            $existing=$this->existing($entity, $owner, $key, $hash);
            if ($existing !== null) return $existing;
            throw $error;
        }
    }

    public function listOwn(int $entity, int $owner): array
    {
        $q=$this->pdo->prepare('SELECT document_json FROM oe_draft WHERE entity=? AND owner_id=? ORDER BY rowid');
        $q->execute(array($entity,$owner));
        return array_map(function ($json) { return json_decode($json, true, 512, JSON_THROW_ON_ERROR); }, $q->fetchAll(PDO::FETCH_COLUMN));
    }

    public function findOwn(int $entity, int $owner, string $id): ?array
    {
        $q=$this->pdo->prepare('SELECT document_json FROM oe_draft WHERE entity=? AND owner_id=? AND id=?');
        $q->execute(array($entity,$owner,$id)); $json=$q->fetchColumn();
        return $json === false ? null : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function existing(int $entity, int $owner, string $key, string $hash): ?array
    {
        $q=$this->pdo->prepare('SELECT payload_hash,document_json FROM oe_draft WHERE entity=? AND owner_id=? AND submission_key=?');
        $q->execute(array($entity,$owner,$key)); $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if (!hash_equals($row['payload_hash'], $hash)) throw new DomainException('Submission key already exists with different intent.');
        return json_decode($row['document_json'], true, 512, JSON_THROW_ON_ERROR);
    }
}
