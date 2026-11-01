# Finens visual assets

Local assets only; no runtime CDN requests. Original reference uses Font Awesome6.5.1 and declares Cairo. The rendered Tailwind font-sans body overrides that declaration; the restored default follows the actual system-sans computed stack. Cairo is retained locally as the original declared face.

Sources:
- Cairo: https://github.com/google/fonts/tree/main/ofl/cairo (OFL license included).
- Font Awesome6.5.1: https://github.com/FortAwesome/Font-Awesome/tree/6.5.1 (full license included).

Solid CSS paths point to the local woff2; the unused TTF fallback was removed. FontAwesome CSS is otherwise upstream. Downloaded static fonts/styles are not an application dependency install.

| File | SHA-256 |
| --- | --- |
| `Cairo-OFL.txt` | `aa2b13bf0770c59a7fb7a3b793a7df5162c9cb5ad540ec81b0c0c1385946845d` |
| `Cairo.ttf` | `667c987182391c91f4e57a2f455b1794fb5e3ee6ca4ef3383e86bb690fa9c964` |
| `Font-Awesome-LICENSE.txt` | `0aa8f86525273b2efa4f40f4272a188e187704252170e979dc06879adf68d43c` |
| `fa-solid-900.woff2` | `1fbdfd5b5eeb365202f30b045bf3ba5e64cce0dddf1c58af74a4feb0158a03a2` |
| `fontawesome-solid.css` | `54ad84119136ebdb7ee0e87fb74a0316cc8f4add6dc2a33d17952fdba30f8842` |
| `fontawesome.css` | `8eb5b44ce5971a501e5a17954c464b80aba0c4604675b5603a7f456459af88a1` |

Cairo license line endings/trailing spaces are normalized; license wording is unchanged.
