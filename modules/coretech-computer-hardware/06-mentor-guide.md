# MENTOR GUIDE

**Purpose:** Guide for mentors assessing learner responses to the [scenario bank](05-scenario-bank.md).

**Format:** Detailed answer keys for the seven mentor-assessed scenarios, plus general assessment guidance that applies across all scenarios.

> **Coverage note.** Keys are present for every **mentor-assessed** scenario: **F3, C1, C2, C3,
> C4, C5 and P3**. The C3 key came from the delivered Cypher material; F3, C1, C2 and P3 were
> written for the 2026-07-28 revision; C4 and C5 are new scenarios from that revision.
>
> Keys for **F1, F2, I1, I2, I3, P1, P2 and P4** are not present. Those are worked as guided or
> optional practice with sample answers embedded directly in the lesson files (Module 2 §2.5 and
> Activity 2.2 contain full worked answers for F1, F2 and I1), or are reserve-pool scenarios not
> formally assessed. Use the general guidance below for any of them.

---

## F3: The Charging Problem — Answer Guide

*(Priya, rural India — first mentor-assessed submission, from Module 2 §2.5.)*

**What's being tested:** whether the learner can distinguish between three candidate causes that produce an identical symptom, and whether they connect the environmental context to the failure. This is the first thing they submit, so assess generously on polish and strictly on *method*.

**The three candidates, all consistent with "plugged in, not charging":**

1. **Battery degradation** — most likely. Two years old in a workspace regularly at 35–40°C is a hard life for a lithium cell; heat roughly doubles the degradation rate for each ~10°C above room temperature. A battery too degraded to accept charge reports exactly this.
2. **Power adapter failure or under-delivery** — voltage fluctuations stress adapters, and a partially failed adapter may power the machine while lacking the headroom to charge as well. A counterfeit or under-wattage replacement adapter behaves the same way.
3. **Charging circuitry / port damage** — least likely without physical trauma, but voltage fluctuation damage does reach the charge controller.

**Distinguishing tests — the core of a good answer:**

- **`powercfg /batteryreport`** (Windows) — compare design capacity against full-charge capacity. A large gap is direct confirmation of degradation, and this is the single most decisive test. Strong answers name it.
- **Substitute a known-good adapter** — if it charges, the adapter was the fault. This is why a spare adapter belongs in the field kit.
- **Check the adapter's temperature and its label** — a working adapter under load is warm; stone cold suggests it's dead. Confirm the wattage matches the laptop's requirement.
- **Wiggle the connector / try another outlet** — cheap, rules out port and outlet.
- **Look for swelling** — a two-year-old battery in a 40°C room is a genuine swelling candidate, and this makes the case a safety question rather than a convenience one. **A learner who checks for swelling here is thinking well ahead of the syllabus at this point; credit it strongly.**

**Immediate recommendations:** confirm the diagnosis before ordering parts; if degradation is confirmed and power is reliable enough, she can work plugged in while a battery ships; if the adapter is at fault, that's a cheaper and faster fix.

**Long-term prevention — where the environmental thinking shows:** address the *voltage fluctuations* (this is an AVR site, not a surge-strip site — though note learners at this point have only met Module 2, so don't penalise "surge protector" here; do raise it in the debrief and flag that Module 4 revisits it); reduce heat exposure; enable the vendor charge limit so a desk-bound user isn't holding a hot pack at 100%; buy only a properly specified replacement battery, never a suspiciously cheap one.

**Common mistakes:** naming one cause and stopping; ordering a battery before testing; missing the environment entirely and treating it as a component fault with no context; proposing no test that would distinguish battery from adapter.

---

## C1: The Cascade of Problems — Answer Guide

*(Lisa, Ethiopia — Module 3 §3.4.)*

**What's being tested:** whether a safety signal overrides the instinct to keep troubleshooting, and whether the learner can plan under a hard deadline with almost no local resources.

**DANGER — the thing that decides this scenario.** Lisa reports **a faint burning smell**. That is a stop sign. The correct answer is that she **must stop attempting to power the machine on**, full stop. A learner who designs an elegant diagnostic sequence that has her keep pressing the power button has failed the scenario regardless of how good the rest is. Say so directly in feedback.

**Symptom analysis — expected content:**

| Symptom | What it indicates |
| --- | --- |
| Battery stopped charging right after the surge | Charging circuit or adapter damaged by the surge — this was the first warning, days ago |
| Power light comes on briefly then dies | Power delivery failing under load; motherboard or power path damage |
| Clicking from inside | Almost certainly the hard drive — mechanical failure, possibly surge-induced |
| Screen black | Consistent with the machine never completing power-up |
| Faint burning smell | A short somewhere. This is the safety finding |

Strong answers note this is **multiple simultaneous failures from one event**, and that the surge is the common cause — the machine took a hit with no protection, and the "it seemed to work" period afterwards was damage already present but not yet fatal. Excellent answers observe that continuing to work on battery power after the charging failure is what consumed the remaining safety margin.

**Urgency assessment:** no further troubleshooting. This machine is out of service. The honest assessment is that it is likely unrecoverable in useful time — surge damage across the power path plus a failing drive on a machine four hours from parts is not a repair Lisa can drive.

**Action plan — look for this ordering:**

1. **Stop. Unplug. Don't power it on again.** Safety first, and it also preserves any chance for the drive.
2. **The data question.** What's on the drive, and is it backed up? If critical translation work exists only there, that is the highest-value thing in the situation — and it may be recoverable by removing the drive and reading it externally, *even though the laptop is dead.* A clicking drive means one careful attempt, imaged, not repeated tries.
3. **Restore work capability inside one week** — this is the actual deliverable. Borrow from the worker two hours away; that contact was given in the brief for a reason, and a learner who doesn't use it has missed a resource. Also check for organisational loaner equipment.
4. **Escalate with documentation** — surge event, symptoms, burning smell explicitly stated, tests not performed and why.
5. **Communicate honestly about the deadline** — if the work genuinely can't be done in a week on borrowed equipment, saying so now is better than discovering it on day six.

**Prevention / systemic changes:** power protection specified to the site (an AVR or line-interactive UPS, not just a surge strip — and if the learner has reached Module 4, expect that distinction); unplug during storms as a habit; backups with physical separation; and the meta-lesson — **the "not charging" message days earlier was the moment to intervene.** Strong answers identify that early warning as the real failure point.

**Common mistakes:** troubleshooting past the burning smell; treating the clicking drive as the main event and missing the safety issue; forgetting the data entirely; ignoring the nearby colleague; no honest conversation about the deadline.

---

## C2: The Gradual Decline — Answer Guide

*(Robert, Cameroon — Module 3 §3.4.)*

**What's being tested:** pattern recognition across time, and a genuinely difficult judgment call where both options are defensible. There is no single right answer to the repair-vs-replace question; there is a right *way of reasoning* about it.

**Pattern analysis — expected content.** The timeline matters more than any individual symptom:

- 3 months: unexplained restarts
- 2 months: program crashes
- 1 month: display artifacts and flicker
- This week: external drive not recognised, explicit "hardware malfunction" error

This is **progressive, multi-subsystem degradation** — power/stability, then display, then I/O. Symptoms spreading across *unrelated* subsystems over months is the signature of a common underlying cause rather than a sequence of coincidences. The strongest candidates:

- **Motherboard degradation** — heat and dust over time, cracked solder joints, failing capacitors. Best fits symptoms appearing across independent subsystems.
- **Thermal damage accumulating** — the hot dusty environment is stated, and heat cycling is exactly what cracks joints and ages components.
- **Power delivery instability** — could produce restarts, crashes, and I/O flakiness.

Look for the learner to say explicitly that this is **not** a single component to swap. A learner who proposes "replace the display" has missed the pattern.

**Urgency:** high, but for the **data**, not the hardware. He hasn't backed up in 10 days, and a machine throwing hardware-malfunction errors could stop booting tomorrow. Immediate action is a backup, today, before any diagnosis.

**The judgment call — how to assess it.** The trade-off is genuinely hard, and both answers can earn full credit:

- **Toward replacement:** 2–3 month lead time means requesting *now* is the only way to have it before the machine dies. Multi-subsystem failure on an old machine in a harsh environment rarely recovers. Six-hour journey to IT makes repair impractical.
- **Toward continuing:** it still works. Three months of degradation suggests it may last a few more. Requesting a replacement doesn't preclude using this one meanwhile.

**The answer that resolves it: do both.** Request the replacement immediately (because of the lead time) *and* keep working carefully on this machine with rigorous backups (because he has to). These are not alternatives — the lead time is what makes them compatible. Credit this strongly; it's the insight the scenario is built around.

**Action plan should include:** back up today and then daily/continuously; verify the backup restores; get irreplaceable material off first; submit the replacement request now with the timeline documented; reduce thermal stress (clean vents safely, improve airflow, cooler location); consider repasting given the age and heat; expect further degradation and plan around it; keep the external drive attached as little as possible if the I/O is flaky.

**Helping Robert decide — communication:** lay out the timeline arithmetic plainly (2–3 months to get a replacement vs. an unknown remaining life measured in weeks-to-months), separate the data risk from the hardware risk so he sees they need different responses, and be clear that requesting a replacement is not giving up on the current machine. Strong answers recognise Robert's actual anxiety is about *losing data*, and that this is solvable today and cheaply — addressing that first will change how he hears the rest.

**Common mistakes:** treating the symptoms as four separate problems; leading with diagnosis instead of backup; recommending a 6-hour journey to IT for a machine that needs replacing; treating repair and replacement as mutually exclusive; missing that the lead time is the decisive variable.

---

## P3: The Data Dilemma — Answer Guide

*(Sofia, rural Bolivia — Module 4 §4.3.)*

**What's being tested:** one thing, sequencing. Everything about this scenario is designed to tempt the learner into the wrong order.

**What's happening:** a clicking drive is a mechanically failing hard drive. The clicking has been going for days, files are now corrupting, and the machine is slow and freezing — the drive is failing *now*, in progress. It could stop responding entirely at any moment, and each power-on is another chance it doesn't come back.

**The correct first action, and the entire point of the scenario:** **stop using the computer and image the drive.** Not repair it, not run CHKDSK, not finish the deadline work, not copy the important folders — image the whole drive, once, and work from the copy.

**DANGER for mentors:** `CHKDSK /R` on this drive is the worst available option and it is the one many learners will reach for, because the course they may have previously taken taught it. It forces a full-surface read/write pass that frequently kills a dying drive mid-scan and can overwrite recoverable data. **A learner who recommends CHKDSK here has failed the scenario's central point**, and this deserves direct, specific feedback rather than a gentle note — the whole scenario exists to catch it.

**Urgency and priority — expected reasoning:** the interviews with elderly speakers are **unrecoverable**. The deadline is in three days and is, in the end, negotiable; the recordings are not. A learner who prioritises the deadline over the data has the values inverted, and should be asked directly: which of these two things can be obtained again later?

**Action plan — look for this shape:**

1. **Stop working on the machine immediately.** Every minute of ordinary use is risk with no benefit. Shut down.
2. **Do not run repair tools.**
3. **Image the drive** — sector-by-sector, ideally with `ddrescue` from a Linux live USB (which she can boot without touching the failing installation). Imaging reads each area once in order and tolerates bad sectors; browsing folders makes the drive seek repeatedly across the most fragile thing it owns.
4. If imaging isn't achievable, **copy the irreplaceable recordings first**, in one pass, largest priority first — not the whole home folder.
5. **Verify the copy** — that the audio files actually play, not merely that they copied.
6. **Then, and only then**, address continuing to work: a borrowed machine, restore from the image, deal with the deadline.
7. **Know when to stop.** If the drive stops responding and the recordings aren't off, further attempts reduce what a professional recovery service could retrieve. That's the point to escalate and talk about a recovery budget, not to try a fourth time.

**Prevention:** backups with physical separation and versioning; treat the clicking sound as an emergency the day it starts, not a week later (name this explicitly — Sofia worked for days through an audible warning, which is the behavioural failure here); recognise that irreplaceable field recordings warrant getting off the capture machine promptly as routine practice, not at backup time; and the archive caveat — a single SSD in a hot cupboard is not long-term storage.

**What exceptional answers include:** explicitly refusing CHKDSK and saying why; naming imaging (and ideally `ddrescue`) rather than file copying; using a live USB so the failing installation is never booted; distinguishing "the deadline" from "the data" and ranking them; a stated stop-point for amateur recovery; verifying the copies play; and compassion in the communication — Sofia is panicking, she made an understandable mistake, and the message she needs first is "stop, don't touch it, we can probably save this."

---

## C3: The Diagnostic Challenge — Answer Guide

**What's Happening:**

This scenario is deliberately ambiguous and could be either hardware OR software (or both). It tests whether learners can think through multiple hypotheses and design systematic diagnostic processes.

### Possible Explanations

**HARDWARE possibilities:**

**1. Failing touchpad:**

- Touchpad becoming hypersensitive or registering phantom touches
- Temperature changes affect touchpad calibration
- Could explain cursor jumping and "touches" when not touching
- "Worse in morning when cold" supports this (hardware behaves differently at different temperatures)

**2. Touchpad palm rejection failure:**

- Not detecting palms vs. intentional touches
- Could cause cursor jumping while typing
- But doesn't explain cursor moving when hands completely off

**3. Physical damage or moisture in touchpad:**

- Temperature extremes could cause condensation
- Morning cold then warming up leads to condensation
- Could create false inputs

**4. Keyboard or touchpad cable issue:**

- Loose connection affected by temperature changes
- Could cause erratic behavior
- Temperature cycling (cold night, warm day) stresses connections

**5. Motherboard issue:**

- Less likely but possible
- Temperature-dependent electrical problems

**SOFTWARE possibilities:**

**1. Malware (despite antivirus):**

- Remote access trojan (RAT)
- Could explain cursor moving and programs opening "on their own"
- Antivirus isn't perfect
- "Things starting on their own" is classic RAT behavior
- BUT: "worse in morning" doesn't fit malware pattern well

**2. Software conflict or driver issue:**

- Touchpad driver corrupted or conflicting
- Recent Windows update causing problems
- Software behaving strangely at startup (timing issue)

**3. Accessibility feature accidentally enabled:**

- Mouse Keys or similar feature
- Could cause unexpected cursor behavior
- Less likely given description

**4. Background process interfering:**

- Some program taking control at specific times
- Scheduled task running in morning
- Could explain timing pattern

**BOTH (hardware + software):**

**5. Hardware problem triggering software response:**

- Touchpad malfunction causing OS to misinterpret inputs
- Fits most of the symptoms

**POWER-RELATED (the hypothesis most learners miss):**

**6. Ungrounded mains supply causing capacitive interference with the touchpad:**

- Two-prong outlets with no earth are normal in much of the field, including mountainous Nepal
- A laptop power supply leaks a small current to the chassis by design; with no earth it has nowhere to go, and the chassis floats at a voltage
- The touchpad senses tiny capacitance changes, so a floating chassis produces **exactly these symptoms** — jumping cursor, phantom clicks, pointer drift
- Fits *"even when my hands are only on the keyboard"* better than palm rejection does
- Fits **"worse in the morning"** at least as well as temperature: mornings are when the machine has been plugged in charging overnight and is still on mains, and morning is also when generator or grid load patterns differ most
- Explains why the antivirus finds nothing and why the user's care about downloads is irrelevant

**This is the cheapest hypothesis to test and should be tested first** — see Phase 2 below. A learner who reaches for it, or who designs a test that would incidentally catch it, is thinking about the user's actual environment rather than working down a generic checklist. Credit that highly.

Note for mentors: this hypothesis was **absent from the delivered version of this course**. Learners who took the earlier version won't have met it, so don't treat its absence as a failure — but do teach it in the debrief, because it recurs constantly in the field and is trivially cheap to rule in or out.

### Systematic Diagnostic Process

Strong answers will design a **methodical elimination process:**

**Phase 1 — Gather more information (questions to ask Kenji):**

**1. Clarify the "on its own" behavior:**

- What exactly happens? (cursor just moves, or clicks happen too?)
- Which programs open?
- Does it happen at specific times or randomly?
- Does it ever happen when he's watching but not touching anything?

**2. Temperature correlation details:**

- What's the morning temperature vs. during day?
- Does behavior improve as computer warms up, or stay bad all day?
- Has he noticed condensation on or around computer?

**3. Timing patterns:**

- Only first thing in morning, or other times too?
- How long after starting computer does it begin?
- Does restarting help or make it worse?

**4. Recent changes:**

- Any new software installed?
- Any Windows updates recently?
- Any physical incidents (drops, spills)?
- Working in new location?

**5. Antivirus and security:**

- What antivirus? When last updated?
- Full scan recently?
- Any security warnings ever?

**Phase 2 — Simple tests (non-invasive):**

**6. Run on battery, unplugged — do this first.**

- Have Kenji unplug the adapter and work on battery alone for several minutes
- **Cursor settles on battery, misbehaves plugged in → ungrounded supply / power path**, not the touchpad
- Misbehaves equally either way → the power supply is exonerated and the remaining hypotheses stand
- Costs nothing, needs no tools, takes one minute, and eliminates an entire branch of the tree
- Strong answers put a test like this early precisely *because* it's free; a learner who sequences expensive or invasive tests ahead of it has missed something about test design

**7. Disable touchpad test:**

- External USB mouse + completely disable touchpad in settings
- If problems stop then touchpad hardware issue
- If problems continue then NOT touchpad (malware or other cause)
- **This is the key diagnostic test for separating hardware from software**

**8. Safe Mode test:**

- Boot into Safe Mode
- Does behavior occur in Safe Mode?
- If NO then software/driver issue
- If YES then more likely hardware

**9. Temperature test:**

- Let computer warm to room temperature before starting
- Does it change behavior?
- Work in warmer location one morning - does it help?

**10. Touchpad settings check:**

- Adjust sensitivity settings
- Disable tap-to-click
- Change palm rejection settings
- Does any setting change the behavior?

**Phase 3 — Software investigation (if touchpad test suggests software):**

**11. Malware scan with multiple tools:**

- Second opinion scan (Malwarebytes or similar)
- Check startup programs
- Review recent software installations
- Check browser extensions

**12. Driver investigation:**

- Update touchpad drivers
- Roll back recent driver updates
- Check for Windows updates

**13. Process monitoring:**

- Watch what processes run in morning
- Check scheduled tasks
- Monitor network activity

**Phase 4 — Hardware investigation (if tests suggest hardware):**

**14. Physical inspection:**

- Look for condensation, especially in morning
- Check for touchpad damage
- Feel for unusual heat in touchpad area

**15. Environmental controls:**

- Store computer in warmer location overnight
- Use sealed bag with desiccant if condensation suspected
- Eliminate temperature cycling

### Solution Based on Most Likely Cause

**If touchpad hardware issue (a strong fit — but test the power path first, since the ungrounded-supply hypothesis fits the same evidence and costs nothing to rule out):**

*Immediate solutions:*

- Disable built-in touchpad completely
- Use external USB mouse permanently
- Adjust storage to minimize temperature cycling

*Long-term solutions:*

- Touchpad replacement (if available/affordable)
- Environmental controls (warmer storage, less temperature swing)
- Continue with external mouse (practical workaround)

*Why this fits well:*

- Temperature correlation (hardware affected by cold)
- Cursor behavior even when not typing (rules out palm rejection)
- "Worse in morning" fits hardware temperature sensitivity
- Mountain location = extreme temperature cycling

**If ungrounded supply (test 6 shows the cursor settles on battery):**

*Immediate:*

- Work on battery for precision typing, as an instant free workaround
- Try a different outlet, ideally one known to be properly earthed

*Longer term:*

- Have the outlet's earth checked and corrected — this is a building electrical fix, not a computer fix
- If no earthed outlet exists anywhere, this is a site-level finding worth escalating: it affects every machine there, not just Kenji's, and it also bears on power protection generally (see Module 4)

*Why learners should like this outcome:* it explains all the symptoms, costs nothing to confirm, and the fix is unrelated to the laptop. It's a good illustration that the answer is sometimes not in the machine at all.

**If malware (less likely but possible):**

*Immediate:*

- Full system scan with multiple tools
- Check all startup programs
- Review installed software
- Change all passwords
- Disconnect from network while investigating

*If confirmed malware:*

- Full system cleanup or reinstall
- Restore from clean backup if available
- Review security practices

### Alternative Hypothesis if Initial Diagnosis Wrong

Strong answers will show contingency thinking:

> "If disabling the touchpad doesn't resolve the cursor movement, then we're likely looking at malware despite the antivirus. The next step would be intensive malware investigation with multiple scanning tools and process monitoring. The temperature correlation might be coincidental, or the malware might be triggered by specific startup conditions that happen to occur more in the morning."

OR:

> "If malware scans come back clean and the external mouse also shows strange behavior, we'd need to investigate motherboard or system-level issues, which would likely require professional diagnosis or replacement."

### Common Mistakes to Watch For

- Jumping to conclusion without systematic investigation
- Not designing an elimination process
- Missing the touchpad disable test (key diagnostic step)
- **Never considering the power supply at all** — treating "erratic input" as necessarily a touchpad-or-malware question
- **Sequencing costly or invasive tests ahead of free ones.** The battery test and the touchpad-disable test between them cut the hypothesis space in half and cost nothing; a plan that opens the case before running either is poorly ordered even if it eventually arrives somewhere sensible
- Not considering temperature as genuine hardware factor
- Dismissing malware too quickly because antivirus is running
- Not having contingency plan for wrong initial diagnosis
- Overcomplicated diagnosis when simple test (disable touchpad) would clarify
- Not thinking about practical workarounds (external mouse)

### Teaching Points

- Ambiguous symptoms require systematic elimination, not guessing
- Design tests that clearly distinguish between hypotheses
- Environmental factors (temperature) genuinely affect hardware
- Simple tests (disable touchpad) can quickly narrow possibilities
- Always have "if I'm wrong" backup hypothesis
- Good diagnosis is about process, not jumping to answers
- Some problems have simple workarounds even if root cause unclear
- Remote diagnosis means designing tests user can perform
- Multiple possible explanations = need decision tree, not single answer

### What Exceptional Answers Include

- Clear acknowledgment that this could be multiple things
- Well-structured diagnostic decision tree
- Specific test that distinguishes hardware from software (touchpad disable)
- Questions to gather more information before recommending tests
- Recognition of temperature as legitimate diagnostic clue
- Practical workaround (external mouse) while investigating
- Contingency thinking ("if this doesn't work, then...")
- Both hardware and software investigation paths
- Appropriate level of skepticism about antivirus (not foolproof)
- Environmental context (mountain location, temperature extremes) informing diagnosis

### This Scenario Tests

- Tolerance for ambiguity
- Systematic diagnostic thinking
- Hypothesis generation
- Test design to distinguish between hypotheses
- Recognition that environmental factors matter
- Ability to create decision trees, not just linear troubleshooting
- Knowing when you need more information
- Having backup plans
- Practical problem-solving (workarounds)

This is perhaps the most advanced diagnostic challenge because there's no single "right answer" - it's about the process and thinking, not reaching a predetermined conclusion.

---

## C4: The Site Survey — Answer Guide

**What's being tested:** whether the learner can specify power protection to a *profile* rather than reciting "get a surge protector," and whether they design data protection that a real person will actually follow.

**The power profile, decoded.** Each detail in the brief points to a different requirement, and strong answers will separate them rather than treating "bad power" as one thing:

| Detail in the brief | What it means | What it requires |
| --- | --- | --- |
| Lights dim when the mill runs | Voltage **sag** under load | AVR — a surge protector does nothing here |
| Outages most days, minutes to hours | Loss of supply | UPS for the short ones; battery/solar thinking for the long ones |
| Two-prong outlets | No earth | Electrical work; expect the tingle and touchpad symptoms |
| Shared diesel generator | Transients on start/stop/changeover | Working habits + AVR |
| Wet-season electrical storms | **Surges** | Surge protection, treated as a consumable |

**Model answer on equipment:** a **line-interactive UPS** per workstation (or one covering the group), because it is the only option that addresses sag *and* outage together; the "line-interactive" qualifier matters because a cheap standby UPS won't regulate voltage. Surge protection is included in most such units, but its MOVs are consumable and need replacing — say so. Cheaper alternatives and what they fail at: a surge strip alone leaves the mill-induced sags and all the outages unaddressed (and the sags are what will kill the adapters); an AVR alone fixes the sags but users still lose work several times a day, and the wet-season storms are still a risk if it lacks surge protection.

Excellent answers will also note that with outages lasting **several hours**, no UPS bridges that — so the real design question is whether laptops simply run on battery through long outages, which makes **battery health** a first-class concern and points at spare batteries, charge-limit settings, and enough charging capacity.

**Grounding — expected content:** two-prong outlets mean chassis leakage has no path to earth. Consequences to warn the team about *in advance*: (a) a harmless-but-alarming tingle on metal-cased laptops, (b) **erratic touchpad behaviour that will be misdiagnosed as a faulty touchpad or malware** if nobody knows to expect it, and (c) it undermines surge protection, since surge devices divert energy to earth and can't do so properly without one. The best answers recommend having an electrician establish a proper earth as part of setting the office up — it is far cheaper before people move in — and tell the team the battery test so they don't chase phantom hardware faults later.

**Generator practice — expected content:** unplug and run on battery through start-up, shutdown and changeover; don't share a circuit with the clinic's motor loads if avoidable; AVR between generator and equipment. Credit any answer that recognises changeover as the dangerous moment rather than steady running.

**Data protection — expected content:** oral histories are irreplaceable and cannot be re-recorded, so this is the highest-stakes part of the answer. Look for: multiple copies with **physical separation** (not a backup drive in the same bag); recognition that slow internet means prioritising *what* gets off-site rather than "back up everything"; a periodic physical courier of a drive being a legitimate off-site strategy where bandwidth isn't; versioned rather than purely synced copies; **restore testing**; and the encryption question — if these laptops are imaged with BitLocker/FileVault, IT must hold the recovery keys, and setting that up belongs in the deployment, not after the first failure. Strong answers also flag that a single archive SSD in a hot cupboard is not safe long-term storage.

**Question 5 — what would you ask.** There is no fixed list; assess whether the questions would change the recommendation. Good examples: What's the actual measured voltage range (is a cheap plug-in voltage monitor worth sending)? What's the budget, and is it per-laptop or total? Can equipment be procured and serviced locally, or does everything ship in — because an unserviceable UPS with a dead battery in two years is worse than no UPS? Who will own the maintenance (replacing MOV strips, testing restores)? Are the laptops' RAM/storage/battery even serviceable on these models? Is there mobile data as a backup path for small critical files?

**Common mistakes:** recommending a surge protector for the whole profile; recommending a UPS without noticing hours-long outages exceed its scope; ignoring grounding entirely; designing a backup regime requiring bandwidth the site doesn't have; forgetting encryption keys; treating this as a purely equipment answer with no working habits or ownership attached.

---

## C5: The Reassuring User — Answer Guide

**What's being tested:** whether the learner can hear a **safety emergency inside a casually worded message**, correctly refuse the question they were asked in favour of the one that matters, and then communicate that without frightening or shaming the user.

**The correct triage — this is the whole scenario.** Amina asked about random shutdowns. The urgent item is the **swelling battery**, and every symptom she dismissed is evidence for it:

- Sustained heat over months → accelerated lithium degradation
- **Kept plugged in at 100% constantly, in a hot room** → the single worst thing for battery longevity
- Battery life collapsed to ~20 minutes → the cell is badly degraded
- **Trackpad has gone stiff** → the pack is expanding and pushing up from directly beneath it
- **Laptop sits unevenly / won't sit flat** → the bottom cover is bulging. It isn't the desk.

These are not five problems. They are **one mechanism with five symptoms**, and a learner who assembles them into a single story has done the main thing this scenario asks. The random shutdowns are plausibly a *sixth* symptom (a failing pack unable to deliver stable power), which is the neat part: answering the safety question may resolve the question she actually asked.

A learner who dives into shutdown diagnosis — event logs, thermal monitoring, memory tests — while a swelling battery goes unremarked has **failed the scenario**, however technically competent the rest is. Say so plainly in feedback; this is exactly the failure mode the stop-sign material exists to prevent.

**First three instructions — model answer:**

1. **Shut it down and unplug it now, and don't charge it again until we've checked something.** Give the reason immediately — don't issue an alarming instruction with no explanation.
2. **Look at it on a flat table and tell me what you see:** does it rock? Is there a gap along the seams? Is the bottom bulging? Press nothing.
3. **If the battery is removable and comes out without any force, take it out** — the laptop will run on mains without it. If it resists at all, leave it alone and we'll arrange a replacement.

Credit answers that get the *ordering* right (stop the hazard before gathering information) and that explicitly warn against pressing, prying, or forcing.

**The shutdowns, once the battery is handled:** candidates to distinguish — a failing battery unable to deliver stable current (test: does it still shut down on mains with the battery removed?); thermal shutdown (test: monitor temperatures under load, remembering that throttling and shutdown are different severities and that a still fan at idle is normal); bad power at the wall in a hot inland office (test: does it correlate with time of day or other equipment starting?); and the possibility that removing the battery ends the shutdowns entirely, which is itself the diagnosis. Good answers name a distinguishing test per hypothesis rather than listing causes.

**Prevention — expected content:** enable the vendor **battery charge limit** so a desk-bound user isn't holding a hot pack at 100% indefinitely; improve the thermal situation (hard surface, airflow, coolest available spot, vent cleaning done safely); consider whether the room itself can be improved; check what she replaces the battery with, because a counterfeit will repeat this faster.

**Communication — what good looks like:** name the seriousness without drama, put the cause on the environment rather than on her ("this is what heat does to batteries here — it's very common and it isn't something you did wrong"), explicitly credit her for reporting the details even though she thought they were unimportant, and close with the reassurance that is *true*: the laptop is probably fine and this is most likely a battery replacement, not a lost machine. Watch for two failure modes — under-communicating urgency to avoid alarming her, and over-communicating in a way that implies she was negligent. She reported every relevant symptom accurately; she just didn't know what they meant. That's the consultant's job, not hers.

**Teaching point for the debrief:** users routinely bury the important detail behind a reassurance — "otherwise fine," "no big deal," "I think that's just the desk." Train yourself to read past the framing to the observations. Amina's message contains a complete and accurate description of a swelling battery; nothing was missing except interpretation.

---

## GENERAL ASSESSMENT GUIDANCE

### What You're Evaluating Across All Scenarios

**1. Diagnostic Reasoning:**

- Do they think systematically or jump to conclusions?
- Do they consider multiple possibilities?
- Do they ask clarifying questions?
- Do they use context clues effectively?

**2. Technical Knowledge:**

- Do they understand hardware components and how they fail?
- Do they recognize common failure patterns?
- Do they know when problems are related vs. independent?

**3. Practical Judgment:**

- Do they recommend appropriate solutions for the context?
- Do they consider resource constraints (remote location, parts availability)?
- Do they know when to troubleshoot vs. escalate vs. replace?
- Do they think about user capability when suggesting fixes?

**4. Communication:**

- Are their explanations clear?
- Would a language worker be able to follow their guidance?
- Do they explain their reasoning?

**5. Prevention Mindset:**

- Do they think beyond the immediate fix?
- Do they identify root causes (environmental, workflow, etc.)?
- Do they provide actionable preventive advice?

**6. Professional Judgment:**

- Do they understand their role as consultant vs. hands-on technician?
- Do they know their limitations?
- Do they think about data protection and work continuity?

### Providing Feedback

**For Strong Responses:**

- Acknowledge what they did well
- Point out excellent reasoning or insights
- Suggest minor refinements or additional considerations
- Example: "Your systematic approach to isolating the touchpad issue was excellent. You might also consider..."

**For Adequate Responses:**

- Confirm what was correct
- Highlight what they missed or could improve
- Ask probing questions to develop their thinking
- Point them to resources or concepts to study
- Example: "You correctly identified overheating, but let's think about why the environmental context matters here..."

**For Weak Responses:**

- Identify where reasoning went wrong
- Work through the scenario together
- Focus on teaching the diagnostic process, not just the "answer"
- Assign additional practice in areas of weakness
- Example: "Let's step back and apply the four-step framework to this scenario. What would you observe first?"

### Remember

**There are often multiple valid approaches:**

- Assess the reasoning, not whether they match your exact answer
- Assess the process, not just the conclusion
- Context matters - good consultants adapt to circumstances
- The goal is developing thinking skills, not memorizing troubleshooting steps

**Use scenarios to identify learning needs:**

- Where does this learner need more practice?
- What concepts need reinforcement?
- What skills are developing well?

**The mentorship continues beyond these scenarios:**

- These are just the starting point
- Real-world cases will build their expertise
- Document patterns you see across multiple learners to improve the course

---

## SCENARIO DIFFICULTY PROGRESSION

### Foundational (F1-F3)

**What they test:**

- Basic component knowledge
- Simple cause-and-effect reasoning
- Ability to ask diagnostic questions
- Environmental awareness

**Expected skill level:**

- Just completed Module 1-2
- Can identify major components
- Understands basic failure patterns
- Beginning to apply systematic thinking

### Intermediate (I1-I3)

**What they test:**

- Systematic diagnostic process
- Considering multiple possibilities
- Prioritizing tests
- Remote communication skills

**Expected skill level:**

- Completed Module 2
- Comfortable with diagnostic framework
- Recognizes common patterns
- Can design test sequences

### Complex (C1-C5)

**What they test:**

- Decision-making under constraints
- Risk assessment
- Systems thinking
- Professional judgment
- Handling ambiguity

**Expected skill level:**

- Completed all modules (C4 and C5 assume Module 4)
- Ready for real consulting work
- Can handle uncertainty
- Thinks strategically

---

## COMMON LEARNER CHALLENGES

### Challenge 1: Jumping to Conclusions

**Symptom:** Learner immediately identifies one cause without considering alternatives

**Intervention:**

- Ask: "What else could cause these symptoms?"
- Emphasize the Isolate step of framework
- Practice generating multiple hypotheses

### Challenge 2: Over-reliance on Hardware Solutions

**Symptom:** Always recommends replacement or complex repairs

**Intervention:**

- Discuss cost-benefit and resource constraints
- Practice identifying simple fixes and workarounds
- Emphasize context-appropriate solutions

### Challenge 3: Missing Environmental Clues

**Symptom:** Doesn't connect symptoms to environment (heat, dust, humidity, power)

**Intervention:**

- Review Module 1 Section 1.4
- Practice identifying environmental factors in scenarios
- Ask: "What does the context tell us?"

### Challenge 4: Poor Remote Communication

**Symptom:** Instructions are vague or assume too much user knowledge

**Intervention:**

- Practice writing step-by-step instructions
- Review Module 2 Section 2.4
- Role-play remote support scenarios

### Challenge 5: No Prevention Focus

**Symptom:** Only addresses immediate problem, no preventive advice

**Intervention:**

- Ask: "How could this have been prevented?"
- Emphasize consultant role includes prevention
- Review real-world cost of recurring problems
