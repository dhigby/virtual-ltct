# Course Design Document

## Course overview

| Item | Description |
| --- | --- |
| **Title** | CoreTech: Computer Hardware |
| **Competencies addressed** | Computer Hardware |
| **Target outcome level** | With Assistance |
| **SME(s) consulted** | Technical review by Claude (Opus 5) acting as hardware SME, 2026-07-28 — 18 findings, all addressed in this revision. **A human SME must still sign off stage 5** before this revision goes to internal review; the model-specific claims flagged below are the priority checks. Earlier AI reviews: Claude.AI (undated), Gemini (2026-03-28). |
| **Design status** | Approved by Doug Higby on 2026-07-28 |

## Why this course is in the pipeline

This course was **delivered in Cypher for Business** and backfilled into the repo as a faithful
import (see [`process/backfill.md`](../../process/backfill.md) and [`BACKFILL.md`](../../BACKFILL.md)).
Backfilled courses are grandfathered out of the full package.

An SME review on 2026-07-28 found 18 technical issues, including **three where following the
content as written damages hardware or destroys data**:

- CHKDSK taught as a failing-drive diagnostic (it accelerates failure and overwrites
  recoverable data)
- compressed-air instructions that destroy fan bearings and pack dust into the heatsink
- swollen lithium batteries — the primary fire risk in hot climates — absent from the entire course

It also found that the power-protection guidance recommends the wrong equipment for the
dominant field failure mode (surge strips for brownout damage). Fixing these is a **substantive
revision**, which under `process/backfill.md` moves the course out of the grandfathered track
and into the 8-stage pipeline. Hence this design doc.

**Board note:** the *delivered* course remains `Online` in Cypher. This revision moves through
the pipeline in parallel; the board status should not be walked backwards from `Online` until
the revised version is ready to replace it at stage 8.

## Learning objectives

One row per objective. The `Source` column names the level from the
[`competencies/computer-hardware.md`](../../competencies/computer-hardware.md) activity ladder
that the objective draws from. That descriptor has a single component — *"Can effectively use
and troubleshoot current and recent computer and mobile hardware"* — with a
Learner → Advanced Beginner → Practitioner → Trainer ladder.

| # | Objective | Source | Assessed by |
| --- | --- | --- | --- |
| 1 | Learner can identify the major hardware components inside a laptop and explain each one's function | Learner ("identify the various hardware components of a computer and understand their function") | Quiz + Module 1 Activity 1.2a/1.2b |
| 2 | Learner can explain how storage, RAM, CPU, cooling and power interact as a system, and use that to locate a bottleneck | Learner → Advanced Beginner | Quiz + Module 1 Activity 1.3 |
| 3 | Learner can recognise how heat, dust, humidity and power quality stress hardware, and read environmental context as diagnostic evidence | Learner → Advanced Beginner | Quiz + Module 1 Activity 1.4a/1.4b |
| 4 | Learner can distinguish a hardware fault from a software fault using OS-independent tests (BIOS/UEFI, Linux live USB, external monitor) | Advanced Beginner ("spend time with an IT professional while they troubleshoot") | Quiz + Module 2 Activity 2.1 |
| 5 | Learner can apply the Observe → Isolate → Test → Decide framework to turn a vague complaint into a specific, actionable diagnosis | Advanced Beginner | Quiz + Module 2 Activity 2.2 + all scenario submissions |
| 6 | Learner can recognise the six common failure patterns from symptoms, including model-specific POST signalling (beep codes vs. LED blink patterns) and serviceability limits (soldered RAM) | Advanced Beginner → Practitioner | Quiz + Module 2 Activity 2.3 |
| 7 | Learner can guide a remote user through diagnostic and maintenance steps without damaging hardware | Advanced Beginner → Practitioner | Quiz + Module 2 Activity 2.4 |
| 8 | Learner can identify and respond to the three field safety hazards — swollen battery, damaged adapter, burning smell — and knows that each ends troubleshooting rather than continuing it | Practitioner (professional judgment) | Quiz + Module 4 Activity 4.2 + scenarios C1, C5 |
| 9 | Learner can specify appropriate power protection for a site's power profile — surge strip vs. AVR vs. line-interactive UPS — and account for generator, inverter and grounding conditions | Practitioner ("recommend appropriate hardware for language program use") | Quiz + Module 4 Activity 4.1 + scenario C4 |
| 10 | Learner can protect irreplaceable data through backup discipline, encryption-key custody, and correct handling of a failing drive (image first, never CHKDSK) | Practitioner | Quiz + Module 4 Activity 4.3 + scenario P3 (assessed, Module 4 §4.3) |
| 11 | Learner can make and justify repair / escalate / replace decisions under real resource constraints, and design workarounds that keep a user working | Practitioner ("troubleshoot hardware issues, and recommend appropriate hardware") | Quiz + Module 3 Activity 3.1/3.2 + scenarios C1–C3 |
| 12 | Learner can assemble a practical field toolkit — diagnostic software, physical tools, documentation templates and a contact network | Practitioner → Trainer | Quiz + Module 3 Activity 3.3 |

## Module breakdown

| File | Topic | Objectives covered | Estimated minutes |
| --- | --- | --- | --- |
| `01-module-1.md` | Understanding Laptop Systems (components, system interactions, environmental factors) | 1, 2, 3 | 65 |
| `02-module-2.md` | Diagnosing Hardware Problems (hardware vs. software, the four-step framework, six failure patterns, remote troubleshooting) | 4, 5, 6, 7 | 85 |
| `03-module-3.md` | Practical Decisions and Application (repair/escalate/replace, resource constraints, toolkit, complex scenarios) | 11, 12 | 65 |
| `04-module-4.md` | Power, Safety & Data Protection in the Field | 8, 9, 10 | 70 |
| `05-scenario-bank.md` | 15 assessment scenarios | 5, 8, 9, 10, 11 | 15 |
| `06-mentor-guide.md` | Assessment criteria and answer keys | — | — |
| `07-quiz.md` | Assessment (27 questions) | 1–12 | 20 |
| `consultant-triage-card.md` | Job aid submitted with every scenario | 8, 10 | — |
| **Total learner seat time** | | | **320** |

**No video script yet.** It is the one remaining stage-3 artifact and is deliberately deferred
until the human SME sign-off at stage 5, because scripting content that may still change wastes
the effort. `check_course_package.py` reports its absence as a warning, not an error. It is a
known gap, not an oversight.

Module 4 is **new in this revision**. It exists because the SME review's four highest-severity
gaps — battery swelling, power protection specification, generator/inverter/grounding, and data
protection — form one coherent topic that Modules 1–3 had no room for without breaching the
90-minute lesson cap. Module 2 grows to 85 minutes, which is at the practical limit; any further
additions must split rather than overflow.

The triage card is deliberately **outside the `NN-` numbering** so
`scripts/check_course_package.py` does not treat a one-page job aid as a lesson and demand
duration and phase headings from it.

## Assessment plan

A **27-question quiz, 80% (22/27) to pass**, covering all 12 objectives. Weighting follows
consequence rather than page count: the safety and data-protection objectives (8, 9, 10) carry
9 of the 27 questions despite Module 4 being one lesson of four, because those are the items
where a wrong answer burns a laptop or loses irreplaceable language data. Objectives 11 and 12
are only lightly sampled (one question each) because judgment-under-constraint and a produced
reference guide are assessed properly by scenario and artifact, not by multiple choice. Several
questions are written as *corrections* of the plausible-but-wrong answer the previous version of
this course would have produced (CHKDSK on a clicking drive, surge strip for brownouts, beeps as
the only POST signal) — that is intentional, since the delivered course taught those.

Hands-on assessment is the 15-scenario bank, worked with a
[Consultant's Triage Card](consultant-triage-card.md) and submitted to a mentor. **Mentor-assessed
scenarios are F3 (Module 2), C1–C3 (Module 3), and P3, C4 and C5 (Module 4)** — the mentor guide
carries a full answer key for each of those seven. F1, F2, I1 are worked as guided practice with
sample answers embedded in Module 2; I2 and I3 are optional extra practice; P1, P2 and P4 are a
reserve pool. The bank carries a where-used table so nothing is silently unused.

## SME knowledge notes

Findings from the 2026-07-28 SME review, recorded here as the contract for content. Numbering
matches the review.

### Corrections to delivered content

1. **CHKDSK is a filesystem tool, not a drive-health tool.** On a mechanically failing drive
   `CHKDSK /R` forces a full-surface pass that often finishes the drive off and overwrites
   recoverable data. Correct order on a clicking drive: stop using it → read SMART → image the
   whole drive → only then consider filesystem repair. CHKDSK's legitimate use is repairing
   filesystem corruption on a *healthy* drive after power loss — keep that, relabelled.
2. **Swollen batteries.** Field-checkable signs: trackpad stops clicking or feels raised, bottom
   cover bulging, laptop rocks instead of sitting flat, keyboard deck lifting, lid won't close
   flush. Response: stop charging, stop using, do not press or puncture, never in checked
   luggage or on aircraft, store cool and away from flammables pending disposal.
3. **Compressed air destroys fans.** Blasting a vent spins the fan past its rated RPM (wrecking
   the bearing) and packs dust deeper into the heatsink fins. Hold the fan blade still with a
   toothpick or cotton bud, or blow only against the exhaust direction. Keep the existing
   "brush away from the openings" and "don't invert the can" advice — both correct.
5. **Beep codes are obsolete on most modern laptops.** Machines built since roughly 2015 largely
   have no PC speaker and signal POST failures via **LED blink patterns** — Dell amber/white
   blink counts, HP caps-lock/num-lock blinks, Lenovo power-button blinks. Teach both, and teach
   looking the pattern up for the specific model. ⚠️ **Human SME: spot-check the per-vendor
   patterns against current vendor documentation before publication.**
6. **Soldered RAM.** Most thin-and-lights, all Apple Silicon Macs, and many XPS/Latitude/Surface
   models have non-removable LPDDR memory. "Reseat the RAM" must be preconditioned on confirming
   the model has SODIMM slots (Crucial system scanner or iFixit — both already cited in the
   course).
7. **85°C needs context.** Modern mobile CPUs are designed to run to ~95–100°C and **throttle**
   (symptom: slow) rather than shut down; true thermal shutdown is nearer 100–105°C. Sustained
   >85°C under load = a thermal problem worth fixing. Throttling (slow) and shutdown (off) are
   different severities and the course must not blur them.
8. **Zero-RPM idle.** Most modern laptops deliberately stop the fan at idle, so "fan not
   spinning" is not evidence of failure. Load the CPU and watch RPM in HWMonitor before
   concluding the fan is dead.
9. **Dust does not cause static discharge** — it is an insulator and a thermal blanket. The real
   mechanisms are airflow blockage and, with humidity, conductive grime bridging contacts.
12. **OS-independent tests are the strongest hardware/software discriminators and both are
    free.** "Can you reach the BIOS/UEFI setup screen, and does the fault happen there?" —
    failure before any OS loads is near-conclusive for hardware. And a **Linux live USB**: if the
    machine boots and runs cleanly from USB, the hardware is fine and the OS or drive is suspect.
    Needs no internet, which matters given the course's own point about field bandwidth. Connect
    this to the existing Ventoy mention.

### New material (the field-context gaps)

4. **Power protection is three different products.** A MOV surge strip clamps *transient spikes
   only* and does nothing for **brownouts, sags and sustained over-voltage** — which is what
   actually kills power bricks and charging circuits on weak grids, and is exactly what the
   Priya and Lisa scenarios describe. An **AVR** corrects sag/swell; a **line-interactive UPS**
   adds a battery and rides through the outages the course keeps citing. Also: **MOVs are
   consumable and fail silently** — in a high-surge area a surge strip is a 12–24-month
   replaceable item, and cheap units have no end-of-life indicator. Retire the
   "$20 surge protector prevents an $800 motherboard replacement" line; it teaches the wrong
   purchase. Useful reframe: on a laptop the external brick is a **sacrificial buffer** — it
   dies first and takes the hit for the motherboard, so a dead brick after a storm is the system
   working as designed.
10. **Generator and inverter power.** Cheap **modified-sine-wave inverters** make bricks buzz,
    run hot and die early; **generators** swing in frequency and throw load-step spikes when a
    fridge or pump starts. Guidance: prefer pure-sine inverters, run on battery through
    generator start/stop and changeover.
11. **Ungrounded power belongs in Kenji's answer key.** Two-prong outlets with no earth are
    normal in much of the field. The resulting Y-capacitor leakage current causes the "tingle"
    on an aluminium chassis **and is a documented cause of erratic, jumpy touchpad behaviour.**
    It fits Kenji's evidence at least as well as temperature does — mornings are when the
    machine is plugged in after charging overnight — and it is trivially testable remotely:
    **unplug and run on battery; if the cursor settles, it's the power path, not the touchpad.**
    Add as a hypothesis and as the cheap early test in the C3 mentor key.
13. **The toolkit section has no tools.** Physical kit is the constraint-buster for this
    audience: **Philips #00 and T5 Torx** cover most laptops (pentalobe for Apple), plus thermal
    paste, a soft brush, and a manual bulb blower. Small, cheap, shippable — which matters when
    parts have 6–8 week lead times.
14. **Repasting.** "Dried thermal paste" is named as a cause and never revisited. For an old,
    hot, out-of-warranty laptop with no parts pipeline, a $3 tube of paste is among the
    highest-leverage interventions available and ships easily. Belongs in the
    constant-overheating workaround.
15. **Vendor charge limits** implement the course's own "don't leave it plugged in 24/7 in heat"
    advice at zero cost: Lenovo Conservation Mode, Dell Primary AC Use, ASUS 60/80% limits,
    macOS Optimized Charging. ⚠️ **Human SME: verify these setting names against current
    vendor UIs.**
16. **Counterfeit chargers and batteries** are endemic in low-resource markets and are a leading
    cause of both swelling and dead charging circuits. Rule: match OEM voltage, wattage and
    connector exactly; treat an implausibly cheap "OEM" battery as counterfeit.
17. **Full-disk encryption can turn a fixable fault into total data loss.** If the org images
    laptops with BitLocker/FileVault on, a drive swap or recovery is impossible without the
    recovery key, and users routinely don't know they have one. "Do you have your recovery key?"
    goes on the triage card beside "is the data backed up?"
18. **Two data points.** The existing "use internet café/library computers" suggestion is wrong
    for the P3 scenario's irreplaceable interviews with elderly speakers — that data must never
    touch a shared machine; say so. And SSDs deserve better than "SSD wear (less common)": they
    fail **without warning** (no clicking), often going read-only or vanishing outright, and
    **unpowered SSDs stored hot lose charge retention over months to years** — directly relevant
    to archived field drives.

### Deliberately out of scope

- Component-level board repair, reflow, and micro-soldering — beyond the consultant role, which
  is diagnosis and recommendation, not repair.
- Desktop and server hardware. The course is laptop-only, matching what language workers use.
- The advanced discourse of RAID, virtualisation and NAS storage.
- The five additions from the 2026-03-28 Gemini review are **subsumed, not adopted wholesale**:
  its power-quality (§3), sensory-diagnostics (§2) and ESD/tooling (§4) points are covered by
  SME items 4/10/13; its damaged-component photo gallery (§1) is deferred for want of licensed
  images; its intake-form template (§5) already exists in Module 3 §3.3 and is now referenced
  from the triage card.

### Notes for the human SME at stage 5

Priority checks, in order: per-vendor LED blink patterns (item 5), vendor battery
charge-limit setting names (item 15), the 85/95/100–105°C threshold figures (item 7), and
whether the AVR-vs-UPS recommendation matches what the organisation can actually procure and
ship to field locations (item 4). Everything else rests on general hardware mechanisms rather
than model-specific detail.

---

### How this document works

1. **Read** the competency descriptor(s) under `competencies/` for each competency listed above. Lift your learning objectives from their observable criteria and activity ladders, not from thin air.
2. **Interview the SME** before drafting objectives or module breakdown. Ask: real field cases they've seen, what learners most commonly get wrong, what "good" looks like at your target outcome level, any tool-version specifics. Record answers in the SME knowledge notes section.
3. **Enforce the 90-minute rule.** Each numbered module file (01-, 02-, …) has a documented estimated duration, stated at the top as `**Estimated time:** X minutes`, and must not exceed 90 minutes. If your content can't fit, split it into another file rather than overflowing — update this table and the `module-author` will implement it.
4. **Align objectives with assessment.** Every objective in the table above must map to at least one quiz question or scenario. Every quiz question and scenario must trace back to an objective — no orphan assessments.
5. **Outcome-level verb choice.** "Has knowledge" objectives use recognize/explain language; "With Assistance" objectives use perform/configure language.
6. **Hand off.** Once approved, this document becomes a contract: the `module-author` and `quiz-writer` agents will draft content and assessment to fulfill it, not freelance or add unstated competencies.
