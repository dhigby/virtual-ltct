# Legacy content backfill

These courses were delivered in **Cypher for Business** (or on Google Sites / as PDFs) and
came into this repo only as stubs or rough README imports. **Backfill** brings their real
content into the repo so this becomes the source of truth. Procedure:
[`process/backfill.md`](process/backfill.md). Conversion is done by the
[`content-backfiller`](.claude/agents/content-backfiller.md) agent.

Backfilled courses are a **faithful import** — grandfathered, no full pipeline package
required.

**Status values:** `Not started` → `In repo` (converted, PR open/merged) →
`Verified` (a second person confirmed fidelity).

## README-only "content" courses

These are already marked `content_type: content` but have no lesson files — the import was
lossy (garbled headers, `<empty-block/>` markers) or never happened.

| Course | Source to pull from | Status | Verified by |
| --- | --- | --- | --- |
| `coretech-fonts-unicode` | Vimeo: https://vimeo.com/1178428863 | Not started | |
| `coretech-malware` | _(locate in Cypher)_ | Not started | |
| `coretech-os-basics` | _(locate in Cypher)_ | Not started | |
| `coretech-why-keboards-matter` | _(locate in Cypher)_ | Not started | |
| `cross-cultural` | Google Site: https://sites.google.com/sil.org/language-technology-academy/courses/general/cross-cultural | Not started | |
| `data-manipulation-skills` | _(locate in Cypher)_ | Not started | |
| `dbl-submission-for-translators` | _(locate in Cypher)_ | Not started | |
| `edit-ai-drafted-translation` | _(locate in Cypher)_ | Not started | |
| `publish-scripture-early-and-often-print` | _(locate in Cypher)_ | Not started | |
| `software-support-and-troubleshooting-for-translation-teams` | Vimeo: https://vimeo.com/1160540654 | Not started | |
| `teaching-a-language-technology-workshop` | Google Site: https://sites.google.com/sil.org/language-technology-academy/courses/general/teaching-a-workshop | Not started | |

## Stubs

These still point at external material via a banner. Backfill converts them to real content.

| Course | Source to pull from | Status | Verified by |
| --- | --- | --- | --- |
| `analyzing-texts-with-fieldworks` | https://lingtran.net/FLEx-8 | Not started | |
| `biblical-exegesis-with-logos` | _(locate in Cypher)_ | Not started | |
| `bloom` | https://sites.google.com/sil.org/language-technology-academy/courses/lt-apps/bloom | Not started | |
| `dictionary-making-lexicography` | https://sites.google.com/sil.org/dls-lex101 | Not started | |
| `effective-use-of-ai-in-lt-consulting` | _(locate in Cypher)_ | Not started | |
| `intellectual-property-policy-and-practice-for-authors` | https://elearning.sil.org/course/view.php?id=258 | Not started | |
| `introduction-to-mentoring` | https://sites.google.com/sil.org/language-technology-academy/courses/general/mentoring | Not started | |
| `keyman-developer-18-tutorial` | _(locate in Cypher)_ | Not started | |
| `paratext-9-basic-training-course` | PDF: https://lingtran.net/Paratext-9-Course-Manual---PDF | Not started | |
| `planning-training-to-right-size-content` | _(locate in Cypher)_ | Not started | |
| `scripture-app-builder-create-a-scripture-app` | https://sites.google.com/sil.org/language-technology-academy/courses/lt-apps/scripture-app-builder | Not started | |
| `understanding-unicode-fonts-encoding-and-rendering` | _(locate in Cypher)_ | Not started | |

## To review (not clearly in scope)

| Course | Note |
| --- | --- |
| `paratext-quotation-rules` | Marked `content` with no numbered lessons, but its prose and data assets live in `README.md`. Confirm the README content is sufficient; if so, no backfill needed. |

> Fill in the real Cypher URLs for the `_(locate in Cypher)_` rows as you find them.
