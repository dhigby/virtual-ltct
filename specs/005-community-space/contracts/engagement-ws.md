# Contract: `local_ltuse_community_engagement`

A read-only external function (R15). It returns aggregate output only.

Decisions applied: Doug, 2026-10-07, both rounds (plan.md, "Decisions" and "Round 2").

## Declaration

| Field | Value |
|---|---|
| classname | `local_ltuse\external\community_engagement` |
| type | `read` |
| capability | `local/ltuse:administer`, on the existing `ltuse_admin` service (Q21) |
| context | system |
| token | the operator's own `MOODLE_ADMIN_TOKEN`, never `MOODLE_TOKEN` |

## Parameters

| Name | Type | Rule |
|---|---|---|
| `quarter` | string | Must match `^\d{4}Q[1-4]$`. The server computes the bounds in the site timezone. A quarter that has not ended raises `invalid_parameter_exception`. |

There is no free time range. With a free range, two narrow or overlapping windows could be differenced to isolate one person.

## Returns

```json
{
  "quarter": "2027Q1",
  "active_learners": 123,
  "posters": "<5",
  "posts": {"this": 40, "previous": 31, "before": 22},
  "unanswered": {"opened": 12, "unanswered_7d": "<5", "rate": null},
  "scope": {"cohort_spaces": 4, "course_forums": 9}
}
```

## Counting rules (Q16, decided 2026-10-07; readings confirmed in round 2)

- **Forums counted:** the forums of every `ltct:site:cohort:*` space (both shapes) and every `ltct:<slug>:discussion`. The site-wide space is deferred (Q1) and not counted.
- **Learner:** an account holding a Student or `spacemember` enrolment. A mentor-only account is not a learner (confirmed, round 2).
- **Active learner:** a learner with any logged event in the quarter. This is "logged in during the quarter": app sessions use a token and log no `user_loggedin` event (confirmed, round 2).
- **Posters and posts:** distinct learners, under the same definition, who posted or replied in the counted forums, and their posts. The exclusion is per post: a post counts only if its author holds no `teacher` role in that post's course. A mentor who also holds a Student or `spacemember` enrolment (a course they take, or an opted-in Area's space) is a learner for `active_learners`, but their posts in a course or space where they are Course mentor never count (round 2), so mentor activity alone can never meet the base condition.
- **Excluded users:** admin, publisher (`webservice` auth) and guest.
- **A discussion opened in a Problems forum** counts as a question. It is "unanswered" if no other user, a mentor included, replied within 7 days of its first post's `created`. This is the only figure in which a mentor counts (round 2).
- **Raw reads:** the log store, `forum_discussions` and `forum_posts`, listed in `moodle/local_ltuse/README.md` (Principle XI).
- **Log retention:** `loglifetime` 365 days (Q21), so any quarter of the past nine months can be reviewed.
- **Suppression:** a count of 1–4 is returned as `"<5"`. A rate with any input below 5 is `null`.
- **No breakdown** by cohort, Area, organisation or forum.
- The function never returns a userid, a name or a message.

## Errors

Unended or malformed quarter → `invalid_parameter_exception`. Log store disabled → `moodle_exception('logstoredisabled')`.

## Tests

`community_engagement_test.php`:
- suppression at 4 and 5;
- excluded users;
- an unended quarter is refused;
- the asker's own reply does not count as an answer; a mentor's reply does;
- an account with no Student or `spacemember` enrolment is not an active learner;
- a mentor's posts are not counted in `posters` or `posts`, and a quarter where only mentors posted gives `posters` 0, including a mentor who also holds a `spacemember` enrolment in an Area space and a Student enrolment in another course; that mentor's own post as a learner in the other course counts;
- the output schema contains no user field.
