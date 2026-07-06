# Course production tracker checklist (canonical snippet)

This is the **single source of truth** for the tracker checkbox list. The
[course-production issue template](../.github/ISSUE_TEMPLATE/course-production.yml) embeds
this list, and it's what you paste into an existing course's issue when it enters
production. Keep the two in sync — if the pipeline changes, edit this file and the template
together.

To retrofit one of the 28 seeded issues: open the issue in the GitHub UI, click **Edit**,
and paste the block below **beneath** the existing body (don't overwrite it).

```markdown
### Production checklist

Run `/next-step <slug>` in Claude Code any time to see where this course is and what to do next.

- [ ] [1. Design doc drafted](https://github.com/dhigby/virtual-ltct/blob/main/process/stages/01-design.md)
- [ ] [2. Design approved](https://github.com/dhigby/virtual-ltct/blob/main/process/stages/02-approve.md)
- [ ] [3a. Lessons drafted](https://github.com/dhigby/virtual-ltct/blob/main/process/stages/03-draft.md)
- [ ] [3b. Scenario bank + mentor guide drafted](https://github.com/dhigby/virtual-ltct/blob/main/process/stages/03-draft.md)
- [ ] [3c. Quiz written](https://github.com/dhigby/virtual-ltct/blob/main/process/stages/03-draft.md)
- [ ] [3d. Video script drafted](https://github.com/dhigby/virtual-ltct/blob/main/process/stages/03-draft.md)
- [ ] [4. Alignment check passed](https://github.com/dhigby/virtual-ltct/blob/main/process/stages/04-alignment.md)
- [ ] [5. SME fact-check passed](https://github.com/dhigby/virtual-ltct/blob/main/process/stages/05-sme-factcheck.md)
- [ ] [6. Internal review passed](https://github.com/dhigby/virtual-ltct/blob/main/process/stages/06-internal-review.md)
- [ ] [7. Piloted with one learner](https://github.com/dhigby/virtual-ltct/blob/main/process/stages/07-pilot.md)
- [ ] [8. Recorded & published to Cypher](https://github.com/dhigby/virtual-ltct/blob/main/process/stages/08-publish.md)
```
