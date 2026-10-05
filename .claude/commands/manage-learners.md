---
description: Bring learners on, enrol, suspend, move and assign mentors in Moodle, for the site team
argument-hint: [what you want to do]
allowed-tools: Bash(python scripts/ltct_admin.py:*)
---

You help a member of the site team change who is in the LTC Moodle: new accounts, cohort
enrolments, suspensions, moves between organisations, managers and mentors. Every change goes
through [`scripts/ltct_admin.py`](../../scripts/ltct_admin.py) (spec 008), never through the
Moodle admin pages and never by hand-written API calls. Its contract is
[`specs/008-admin-tooling/contracts/cli.md`](../../specs/008-admin-tooling/contracts/cli.md).

What the operator asked for: `$ARGUMENTS`

**These are real people.** The tool shows them to you masked, as row numbers and
`a***@example.org`, and that is all you should ever see. You never read the files that name
them (spec 008 research R14, R16).

## 1. Check the connection

```bash
python scripts/ltct_admin.py check
```

It names the site, the release and the operator's account, and confirms the token can call
every administration function and the server's settings are safe. If it refuses, quote its
message and stop: the fix is the site team's (`site_config.py apply`, or the `ltctadmin`
role), not something to work around.

`MOODLE_URL` and `MOODLE_ADMIN_TOKEN` come from the environment. `MOODLE_ADMIN_TOKEN` is the
operator's **own** "LTC administration" token, never the publisher's `MOODLE_TOKEN`; the tool
refuses if the two are equal. If one is missing, tell the operator to set it in their own
terminal. Never ask them to paste a token into this conversation, and never write one to a
file.

## 2. Ask what they want to do

If `$ARGUMENTS` does not already say, ask. Map the answer to one command:

| They want to | Command |
|---|---|
| bring new learners on | `intake <file>` |
| enrol an organisation's cohort in one course | `enrol course --cohort <idnumber> --course <idnumber>` |
| enrol a cohort in a pathway's courses | `enrol pathway --cohort <idnumber> --pathway <key>` |
| prepare an organisation for people moving into it | `enrol mirror --from <key> --to <key>` |
| take a cohort out of a course (history kept) | `unenrol --cohort <idnumber> --course <idnumber>` |
| suspend or reactivate accounts | `suspend <file>` / `reactivate <file>`, or `--email <address>` for one |
| move learners to another organisation | `move <file>` (after `enrol mirror`) |
| add or remove managers, or the mentors cohort | `managers <file>` |
| assign mentors in bulk, or end one mentor's relationships | `mentors assign <file>` / `mentors end --mentor <address>` |
| record one-course or cohort mentors | `course-mentors <file>` (add `--remove` to remove) |
| see who is in an organisation and where | `summary --org <key>` |
| a blank file to fill in | `template --kind <kind> --out <path outside the repo>` |

**Names come from the tool.** For an organisation key, a cohort or a course, run
`python scripts/ltct_admin.py list organisations`, `list cohorts` or `list courses`
(`--org <key>` narrows the last two), and offer what it prints. Never guess or construct one.

**Files are the operator's.** Ask them for the path of their file. It must be outside every
repository folder; the tool suggests `~/ltct-private/`. If they have no file yet, run
`template` with an `--out` path in that folder and tell them to fill it in themselves.

## 3. Preview

Run the command **without** `--apply`. That changes nothing in Moodle: the server works out
what each row would do and the tool prints counts per outcome, masked rows for anything
flagged, and a confirmation code.

## 4. Explain the preview in plain words

Tell the operator, in a few sentences, what will happen: how many accounts are created, how
many are already there, which rows will be left alone and why, which courses gain or lose
people. Use the tool's own reasons for flagged rows. Point out anything they may not expect,
for example a row flagged as already in another organisation, which needs `move`, not intake.

If the preview printed the **production reminder** (spec 002 R13), repeat it, word for word,
and ask whether any organisation in this change is one the site team has marked as possibly
needing protection. Until identity protection (spec 016) is delivered, such an organisation
is not enrolled into a shared course on production. That marking is kept in Moodle by the
site team; do not ask for it to be written down here.

## 5. Apply only on the operator's yes

Ask: "Apply exactly this?" Only after a clear yes, run the line the preview printed:

```bash
python scripts/ltct_admin.py <the same command> --apply --confirm <code>
```

The second run previews again and refuses if anything the code covers has changed. If it
refuses, preview again, explain what changed, and ask again. Never reuse a code across commands or
files.

The apply goes one row per call and is safe to repeat. If it was cut off, run the same line
again: rows already done report `already done`.

## 6. Output

```
👥 <command> — <applied | previewed, not applied>

✅ <counts per outcome, in the tool's words>
⚠ <rows left alone, by row number, with the tool's reason>
▶ Next:  <the one thing to do next>
```

## Rules

- **Never Read, open, cat or copy an intake file, or any file the operator names.** Pass its
  path to the tool and nothing else. What you need to know, the tool prints.
- **Never pass `--show-people`.** Names and full addresses are for the operator, in their
  own terminal. If they need them, tell them to run the command there themselves.
- **Never put an intake file, a summary or any output inside this repository**, or any other
  repository or worktree. The repo is public and GitDoc pushes what is left in it. Every
  `--out` goes under `~/ltct-private/`.
- **Never invent an organisation key, a cohort or a course.** Offer what
  `ltct_admin.py list` prints.
- **After any refusal, quote the tool's message verbatim.** Don't paraphrase it and don't
  look for another way to make the change: the refusals are the rules (a cohort that may not
  enrol in that course, a move that would lose a course, a row needing protection first).
- **Apply only after a yes to that preview.** No `--apply` without a fresh `--confirm` code
  the operator has seen explained.
- **Production gate (spec 002 R13).** Until spec 016, including its decision 2, is delivered,
  do not enrol into a shared course on production any organisation the site team has marked
  as possibly needing protection. The tool prints the reminder on a shared-course `enrol` and
  an `intake` with courses; repeat it and wait for the operator's answer.
- **Write nothing about the people here.** No learner names, addresses, counts tied to an
  organisation, or protection levels go into the repo, an issue or a pull request
  (constitution III).
