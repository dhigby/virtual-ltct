# Quickstart: verifying the resource library

Build the site the way CI does, then run the checks below.

```bash
pip install -r docs-requirements.txt
python scripts/check_competency_descriptors.py
python -m pytest tests/test_resources_yaml.py tests/test_gen_site_library.py tests/test_check_resource_links.py
python -m mkdocs build --strict -d <somewhere outside the repo>
```

## Search (User Story 1, SC-001, SC-005)

The ten look-up tasks, phrased as a consultant would ask, and the resource each should find:

| # | Query | Should find |
|---|---|---|
| 1 | keyboard install android | a Keyman entry |
| 2 | windows keyboard layout | How to change keyboard layouts in Windows 11 |
| 3 | backup paratext project | a Paratext support or training entry |
| 4 | font missing characters | SIL Fonts or a Unicode character entry |
| 5 | creative commons licence | Creative Commons Licenses |
| 6 | flex dictionary webonary | Webonary |
| 7 | bloom book | Bloom Library |
| 8 | unicode code chart | Unicode Code Charts |
| 9 | two factor authentication | What is two-factor authentication |
| 10 | problem solving skills | a problem-solving guide |

**Local run, 2026-10-06** (the built `search_index.json`, queried with lunr.py, the Python
port of the engine the site uses): **10 of 10** found in the first five results. A resource
listed under several competencies can take two of the five slots (`#webonary`,
`#webonary_1`), which is the main thing a real user may trip on.

**No match (scenario 2)**: Material's search box says "No matching documents". The library
page's opening line points to browsing by competency.

**Page weight (SC-005)**: `library/index.html` is 276 KB raw, 36 KB gzipped; the search
index is 47 KB gzipped and loads only when someone searches. At 256 kbit/s that is about
1.2 s and 1.5 s. Not yet measured in a throttled browser.

**Still to do**: SC-001 with 2–3 real partner users (T015).

## Browse (User Story 2, SC-002)

**Run 2026-10-06** on the built site:

| Competency page | Further Information entries, each with description and type |
|---|---|
| Keyboards | 5 |
| Fonts & Encoding | 8 |
| Literacy Tools | 9 |
| Malware | 7 |
| Mentoring | none, so the section is absent, as the contract says |

- A made-up competency name in `resources.yaml` fails the check: `'Keyboard Magic' is not in
  competencies.yaml`.
- Every resource names at least one framework competency (the check refuses an empty list),
  so every resource is reachable by browsing (SC-002).

## Moodle (User Story 3, SC-003)

Maintainer-run on ltuse.net, with `MOODLE_URL` and the admin token set:

1. `python scripts/site_config.py drift` shows `custommenuitems` and
   `tool_mobile/custommenuitems` differing, then `apply`.
2. Browser: as a test learner, from the Dashboard tap **Library**. It opens `/library/`.
   Search for a named resource and open it. Count the taps: two or fewer.
3. Moodle app: log out and back in so the app reloads its menu. Open the main menu, tap
   **Library**. It opens in the in-app browser.
4. A lesson link to `https://competencies.languagetechnology.org/library/#<anchor>` opens
   at that resource, in the browser and in the app.

Result: _not yet run._
