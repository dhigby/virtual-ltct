# Contract: `scripts/engagement_review.py`

Prints the quarterly figures and the trigger verdict to the terminal. It writes no file, in the repo or anywhere else.

Decisions applied: Doug, 2026-10-07, both rounds (plan.md, "Decisions" and "Round 2"): terminal only, so there is no `--out`; posters are learners only (engagement-ws contract).

## Usage

```text
python scripts/engagement_review.py --quarter 2027Q1 [--asked N --moved-towards M --returned R] [--quarters-without-trigger N]
```

- `MOODLE_URL` and `MOODLE_ADMIN_TOKEN` come from the environment.
- `--asked`, `--moved-towards` and `--returned` are obstacle 2 counts. The operator types them in from coordinators' answers against their recorded baseline. They are counts only, never names.
- `--quarters-without-trigger N` is the number of consecutive quarters immediately before this one in which neither the base condition nor any obstacle signal held. A quarter in which either held resets it to 0. The operator takes it from the decisions recorded in `INTENT.md` and earlier reviews; nothing is stored by the script. Default 0.

## Output (stdout)

The output has these parts:

1. The figures, as returned by the engagement function.
2. The base condition, with one of these results: met, not met, or insufficient data.
3. One line for each obstacle signal, each with one of these results: fired, not fired, or insufficient data.
   - Obstacle 2 fires when 2 or more of the organisations asked report a move towards WhatsApp, or report that people went back to it.
   - Obstacle 3 is printed as "ask partners" and has no figure.
4. A verdict, which is one of these:
   - `RECONSIDER`: record a decision in `INTENT.md`;
   - `NO CHANGE`;
   - `SIMPLIFY-OR-RETIRE CHECK`: neither condition holds this quarter and `--quarters-without-trigger` is 3 or more, so neither has held for four quarters.

## Exit codes

| Code | Meaning |
|---|---|
| 0 | review produced |
| 2 | bad arguments |
| 3 | server or token error |

## Tests (`tests/test_engagement_review.py`, no server)

The tests cover:
- each verdict, including `SIMPLIFY-OR-RETIRE CHECK` at `--quarters-without-trigger 3` and not at 2;
- a suppressed value leading to insufficient data;
- obstacle 2 fires at 2 and not at 1;
- `--out` is refused as an unknown argument, and a run writes no file.
