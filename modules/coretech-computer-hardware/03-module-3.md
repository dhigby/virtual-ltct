# MODULE 3: PRACTICAL DECISIONS AND APPLICATION

**Estimated time:** 65 minutes
**Target Audience:** Trainee Language Technology Consultants
**Format:** Asynchronous self-paced learning

## Learning Objectives
By the end of this module, you will be able to:
- Make appropriate repair vs. replace vs. escalate decisions
- Navigate resource constraints in remote locations
- Build your troubleshooting toolkit
- Handle complex, multi-factor scenarios
- Think systemically about prevention
- Assess your readiness for the consultant role

---

## Connect

In Modules 1 and 2, you built the technical foundation — understanding components, diagnosing problems, and recognising failure patterns. Module 3 is where that knowledge meets real-world judgment.

Think about a time when you knew what was wrong with something but weren't sure what to do about it — the fix was too expensive, or the right parts weren't available, or the situation was too urgent to wait for the perfect solution. That's the challenge this module prepares you for.

In your learning journal, reflect briefly: what do you think is the hardest part of supporting language workers in remote locations — the diagnosis, or knowing what to actually recommend given the constraints they face?

---

## Content

_Same four steps as always — observe, isolate, test, decide. What changes in this module is the last one. Deciding is where technical diagnosis meets the constraints your users actually live with: what parts can reach them, what they can safely attempt themselves, and what their work can't wait for._

### Section 3.1: The Consultant's Judgment Calls
**Time:** 15 minutes (includes Activity 3.1)

You've learned to diagnose hardware problems. But diagnosis is only part of your job. You also need to make **practical recommendations** considering resource availability, user capability, cost vs. benefit, urgency and timelines, and work continuity. These aren't purely technical decisions — they require judgment.

For every problem, you have three possible paths:

#### Path 1: User Can Fix (with guidance)
**When appropriate:** problem is clearly diagnosed, solution is within user capability, risk is low (won't make things worse), you can provide clear guidance, user expresses comfort with the task.

**Examples:** cleaning dust from vents, freeing up disk space, reseating RAM (if the model has removable RAM *and* the user is comfortable), using external mouse for touchpad issue, basic troubleshooting steps.

**How to decide if user can do it:** Can I explain this clearly in writing? What's the worst that could happen if they make a mistake? Does this require special tools? Have they done anything like this before?

**Before routing anything to Path 1, confirm the part is actually serviceable.** Modern laptops are increasingly not. RAM and storage are often soldered to the motherboard, and batteries are frequently glued in rather than clipped. Check the model on iFixit or with the Crucial system scanner *before* you ask a user to open anything. A teardown that ends in "there's nothing here to reseat" costs the user an afternoon, some anxiety, and sometimes a broken clip — and it should have been a five-minute lookup on your side.

**Preparing a user for any internal work — the safety and prep sequence:**

1. **Back up first.** Anything that opens a case might not close successfully. Data comes off before the screwdriver comes out.
2. **Power off, unplug, and if the battery is removable, take it out.** Then hold the power button 30 seconds to drain residual charge.
3. **Discharge yourself.** Touch a bare metal part of the chassis before touching any component. Static damage is much less common than hobbyist forums suggest, but it is not zero — and it rises sharply in exactly the conditions your users work in: dry season, high altitude, synthetic clothing. Working on a hard non-carpeted surface handles most of the risk.
4. **Use the right screwdriver.** A **Philips #00** and a **T5 Torx** cover the great majority of laptops (Apple needs pentalobe). Using a knife blade or an oversized driver strips the screw heads, and a stripped screw turns a 10-minute job into a destroyed bottom cover. If the user doesn't have the right driver, that alone is a reason to choose a different path.
5. **Photograph each step**, and keep screws in order — laptop screws are often different lengths, and a long screw in a short hole punctures the board.
6. **Never force, pry near, or puncture the battery.** If the battery looks swollen, stop entirely and go to [Module 4](04-module-4.md).

**Always provide:** clear step-by-step instructions, safety warnings, photos/diagrams if possible, "if you're not comfortable, it's okay to escalate," and what to do if it doesn't work.

#### Path 2: Escalate to IT Support
**When appropriate:** diagnosis uncertain (need hands-on investigation), repair requires technical skills beyond user, requires special tools or parts, high risk of making things worse, hardware needs replacement but you're confirming first.

**Examples:** display cable repair, motherboard issues, complex multi-component failures, physical damage repair, when your troubleshooting hasn't resolved it.

**How to escalate effectively:** don't just say "I can't help, contact IT." Provide value by documenting what you know — symptoms, tests already done, results, what you've ruled out, your hypothesis.

Good escalation: _"Based on my troubleshooting, this appears to be a display cable issue rather than the screen itself, because an external monitor works fine. The user has already tried reseating the RAM and checking connections. I recommend IT examine the display cable connection. The user can continue working with an external monitor in the meantime."_

Not good: _"I don't know what's wrong, contact IT."_

#### Path 3: Replace Equipment
**When appropriate:** multiple component failures, repair cost approaches replacement cost, computer is old (3–5+ years in harsh environments), imminent complete failure, critical component failed (motherboard etc.).

**Consider replacing when:** 5-year-old computer with failing motherboard, $300 repair on $500 computer, 6 weeks for repair vs. 3 weeks for replacement, user's work can't wait, progressive deterioration suggests more failures soon.

**But also consider:** if replacement takes 3 months, repair might be needed temporarily. Can we get replacement parts/computers? What's actually feasible in the budget? Can user work on borrowed equipment while waiting?

**Sometimes both:** recommend a temporary repair/workaround NOW plus start the replacement request. Example: "The power adapter has failed. Request a replacement adapter (2–3 weeks) but also request a new computer (2–3 months) because this computer is showing signs of progressive failure. The adapter will get you working again quickly, but you'll need new equipment soon."

### Activity 3.1: Make the Call
**Time:** 10 minutes — for each scenario, decide: User can fix / Escalate / Replace? Explain your reasoning in your learning journal before reading the answers.

**Scenario A:** Computer is 6 months old. Screen has developed a single dead pixel (tiny black spot). Everything else works perfectly. User finds it annoying but can work fine.

**Scenario B:** Computer is 4 years old, in dusty environment. Now showing: frequent overheating shutdowns, display flickering, USB ports intermittent, clicking sounds from hard drive. User has critical translation project due in 2 weeks.

**Scenario C:** Computer has three beeps on startup, black screen. Just happened today after user transported it. Computer is 1 year old, otherwise has worked fine.

**Scenario D:** Power adapter cable is frayed near the connector. Still works but sometimes has to be positioned just right. Has been like this for a month.

**Scenario E:** Battery no longer holds charge — only works plugged in. Computer is 3 years old. Battery replacement costs $80, lead time 4–6 weeks. User works in location with reliable power.

**TIP**
**A — No action needed:** dead pixel is cosmetic, repair would require expensive screen replacement. Document in case more pixels fail.

**B — Replace:** multiple failing systems, clicking hard drive = imminent failure risk, 2-week deadline can't wait for complex repairs. IMMEDIATE: back up data, request replacement, minimise use.

**C — User can fix, conditionally:** classic RAM unseated from transport, computer is relatively new (worth fixing), reseating RAM is straightforward *if the RAM is removable*. Check the model on iFixit first — a 1-year-old thin-and-light may well have soldered memory, in which case this becomes an escalation, not a user fix. Walk the user through it if it's serviceable and they're comfortable; escalate otherwise. Good prevention opportunity (padded transport case).

**D — Replace adapter immediately:** frayed cable is a safety hazard (fire risk, shock risk), will fail completely soon. This is urgent — don't let user keep using it.

**E — Depends on context:** if reliable power and no need for portability, live with it. If user needs mobility, replace battery. Also consider: if computer is otherwise near end of life, replace entire computer instead of investing in a battery. Key insight: these aren't always technical decisions — they're practical judgments based on context, resources, risk, and user needs.

**DANGER**
Generalise Scenario D: any visibly damaged power adapter — frayed, cracked, exposed conductor, scorch mark — is a fire and shock risk, and "it still works if I hold it right" is not a reason to keep it in service. Treat visible damage as the failure itself, not a warning of one.

### Section 3.2: Resource Constraints and Workarounds
**Time:** 10 minutes (includes Activity 3.2)

Your users aren't in cities with easy access to computer repair shops. They face limited or no replacement parts locally, weeks or months for parts to arrive, no local technical support, work that can't stop while waiting, and budget constraints. **Your job: help them keep working despite these constraints.**

### Workaround Strategies
1. **Identify what CAN work.** Don't focus only on what's broken. Display broken? Use external monitor. Touchpad issues? Use USB mouse. Battery dead? Work plugged in. Keyboard keys not working? Use external keyboard or on-screen keyboard. Port not working? Use a different port.
2. **Prioritise partial solutions.** A solution that's 80% effective NOW is often better than a perfect solution in 6 weeks. Example: laptop overheats and shuts down after 30 minutes. Perfect solution: replace cooling system (6 weeks for parts). Partial solution now: aggressive cleaning, cooling pad, work in short sessions, save frequently. Result: user can continue working carefully while waiting for parts.
3. **Creative problem-solving.** Can the user borrow equipment from another worker temporarily? Can some work be done on a phone or tablet while the laptop is down? Can two users share one working computer? Is there a regional office that might have spare parts? Can the user travel to the city for repair if the deadline is critical?
4. **Preventive triage.** When resources are limited, prevention becomes even more critical: **power protection matched to the site's actual power profile** (see [Module 4](04-module-4.md) — a surge strip is the cheapest option but addresses only spikes, and on a site with sagging voltage it will not prevent the adapter failures you're trying to avoid), regular cleaning (free, prevents overheating), good backups (essential, enables recovery), careful handling (free, prevents damage). The economics genuinely favour prevention here — a correctly specified AVR costs a fraction of the laptops it protects, and far less than the weeks of lost work when one dies. Just make sure you're buying protection against the problem the site actually has.

### Activity 3.2: Find the Workaround
**Time:** 5 minutes — for each situation, identify a workaround that lets the user continue working while waiting for the permanent solution. Write your answers before reading the sample solutions.

#### Situation 1: Failing Hard Drive
Hard drive is clicking and failing. Replacement will take 6 weeks. User has backed up data. How can they keep working?

**TIP**
If there's another language worker nearby: borrow their laptop temporarily, restore from backup. If organisation has spare equipment: request temporary loaner. If neither available: reduce computer use to only critical tasks, work on a borrowed computer with cloud-based tools.

**WARNING**
**Shared and public computers are not an option for language data.** An internet café or library machine may be fine for answering email, but language documentation data must never touch one. Recordings and transcripts of named speakers, unpublished translation drafts, and community material held under agreement are exactly the data that cannot be allowed to persist on a machine you don't control — and public machines routinely have keyloggers, cached credentials, and files left in temp folders and recycle bins long after the user leaves. There is also usually a consent dimension: speakers agreed to be recorded by a project, not to have their interviews sitting on a café hard drive.

If a shared machine is genuinely the only option, restrict it to work with no personal or community data in it at all, and never sign in to project accounts or cloud storage from it. When in doubt, delaying the work is the safer trade.

#### Situation 2: Constant Overheating
Laptop constantly overheats and shuts down. Fan replacement parts not available locally. User has critical deadline in 2 weeks.

**TIP**
**Immediate cooling measures:** work in coolest location/time of day, careful vent cleaning (brush or bulb blower — hold the fan still if using compressed air), elevate laptop for maximum airflow, work in 20-minute sessions and let cool between, use temperature monitoring and stop before shutdown, cooling pad if available locally.

**Reduce heat generation:** close all unnecessary programs, lower screen brightness, disable background processes, work with fewer programs simultaneously. On Windows, capping the processor at 90–99% of maximum in the power plan disables turbo boost and can drop peak temperatures substantially for a small performance cost — a good trade when the alternative is shutdowns.

**Work process adaptation:** save frequently (expect shutdowns), break work into smaller chunks, do heat-intensive tasks only when absolutely necessary, consider whether some work could be done on phone/tablet.

**The repair nobody thinks of — replace the thermal paste.** Thermal paste is the compound between the CPU and the heat sink that carries heat across the gap. It dries out over about three to five years, faster in heat, and when it does the cooling system can be spotless and still not work. This is a genuine repair, not a workaround, and it is uniquely well suited to constrained settings:

- A tube costs a few dollars and is enough for many laptops.
- It is tiny and ships easily — no customs difficulty, no 8-week lead time on a specific part number.
- It requires no model-specific component. The same tube fixes any laptop.

For a hot 4-year-old machine where a fan is unobtainable, repasting is often the single highest-value intervention available. It does require opening the machine and reaching the heat sink, so it's a Path 1 job only for a confident user, and a good candidate for local hands-on help. Worth stocking proactively in any regional office.

#### Situation 3: Broken Keyboard Keys
Four keys on keyboard don't work (water damage). Keyboard replacement will take 8 weeks. User does a lot of typing.

**TIP**
**External USB keyboard** — often available locally, inexpensive, best solution for desk work. **On-screen keyboard** — built into OS, slower but works everywhere. **Remap keys** — use other rarely-used keys for the broken ones (software solution). **Combination approach** — external keyboard for desk work, on-screen for portable work.

### Section 3.3: Building Your Troubleshooting Toolkit
**Time:** 15 minutes (includes Activity 3.3)

Good consultants don't memorise everything — they build systems for finding answers quickly. Your toolkit has three parts: software you can direct a user to, **physical tools that need to exist somewhere near the user**, and reference material you can find fast.

#### The Physical Kit

This is the part most consultants skip, and in a setting where a specific replacement part takes six to eight weeks to arrive, it's the part that actually unblocks work. Everything on this list is small, cheap, generic, and ships without difficulty — which is precisely why it beats waiting on a model-specific component.

| Item | Why it matters |
| --- | --- |
| **Philips #00 screwdriver** | Opens the majority of laptop bottom covers. The single most useful item here. |
| **T5 Torx driver** | Covers most of the rest. (Apple laptops need a **pentalobe** driver as well.) |
| **Plastic spudger / guitar pick** | Releases clips without gouging the case or shorting a board the way a metal blade can. |
| **Manual bulb blower** | Safe, reusable dust removal. Can't over-spin a fan, unlike a can of compressed air. |
| **Soft brush** | Surface dust on vents and grilles. |
| **Thermal paste** | A few dollars, fixes overheating on any laptop, ships anywhere. |
| **Silica gel packets** | Humidity and condensation control in storage. Rechargeable in an oven. |
| **A known-good spare power adapter** | The fastest way to confirm or eliminate an adapter fault — and the most common failed part. |
| **A Ventoy USB stick** | Your diagnostic environment, and the definitive hardware-vs-software test. |
| **A known-good USB mouse and keyboard** | Instantly works around touchpad and keyboard faults, and isolates them diagnostically. |

Two notes on getting this right. First, a **stripped screw head** turns a ten-minute job into a destroyed bottom cover, so the correct driver size genuinely matters more than it sounds — if the only tool available is a kitchen knife, that's a reason to choose a different path, not to improvise. Second, this kit belongs **where the users are**, not with you. A regional office that stocks these items serves every worker in the region; the same kit in a consultant's desk drawer three countries away helps nobody.

#### Diagnostic Software
**Temperature Monitoring:** HWMonitor (Windows) — shows all system temperatures. Core Temp (Windows) — CPU temperature. Macs Fan Control (Mac) — temperature and fan control.

**Disk Health:** CrystalDiskInfo (Windows) — drive health status. DriveDx (Mac) — drive diagnostics. Built-in tools: CHKDSK (Windows), Disk Utility (Mac).

**System Information:** Speccy (Windows) — complete hardware info. CPU-Z (Windows) — detailed component info. Built-in: System Information (both platforms).

**Battery Health:** BatteryInfoView (Windows). coconutBattery (Mac). Built-in: Battery Report (Windows), System Report (Mac).

**Memory Testing:** Windows Memory Diagnostic (built-in). MemTest86 (advanced, thorough).

**INFO**
**Ventoy** lets you put multiple ISOs (diagnostics, Linux live environments, installers) on a single USB drive — you copy ISO files onto it like ordinary files and pick one from a menu at boot. It is the Swiss Army knife of field support, and it is worth building one before you need it.

What makes it more than a convenience: a **Linux live environment on that stick is the definitive hardware-vs-software test** from Module 2. Boot the machine from USB and you are running a complete, known-good operating system that never touches the installed one. Clean from USB means the hardware is fine and the problem is the OS or the drive. Faulty from USB means hardware, regardless of how many times the user has reinstalled Windows.

It also solves a problem specific to your context: it works **entirely offline**. You build the stick once, where bandwidth exists, and it then diagnoses machines that can't download a single megabyte. Put a Linux live ISO and a memory tester (MemTest86) on it at minimum. If you can get one stick to each region, do that — mailing a USB drive is far easier than mailing a laptop part.

#### Reference Resources
**For specific hardware info:** manufacturer websites (drivers, diagnostics, specifications), [iFixit.com](https://www.ifixit.com) (teardowns, repair guides, identify components), Crucial.com system scanner (identifies compatible RAM/storage).

**For troubleshooting:** manufacturer support forums (others' experiences with same model), Tom's Hardware and AnandTech (detailed technical discussions), Reddit r/techsupport (community troubleshooting), YouTube (visual repair guides).

**For startup (POST) failure codes:** search "[manufacturer name] [model] beep codes" **and** "[manufacturer name] LED blink codes" — each manufacturer uses different codes, and most modern laptops blink an LED rather than beeping at all (see Module 2, Pattern 5). Keep the lookup page for the models your users actually have.

**Quick reference guides to create for yourself:**
- Failure pattern checklist (overheating, battery, display, storage, RAM, power symptoms and checks)
- Diagnostic decision tree: "if symptom X, check Y first, then Z..."
- Common fixes: how to check disk space, run diagnostics, check temperatures, access system info

#### Documentation Templates and Contact Network
**Intake form:** user name/location, computer model/age, symptom (specific), when started, pattern (when happens/doesn't happen), recent changes, environmental factors, tests already tried.

**Troubleshooting log:** date/time, test performed, result, hypothesis, next step.

**Escalation form:** symptom summary, tests completed and results, what's been ruled out, current hypothesis, recommendation, urgency level.

**Build relationships with:** IT support team (who to escalate to, how they prefer information), other language tech consultants (share solutions), regional equipment managers (parts availability), local contacts in each location (who can provide hands-on help).

### Activity 3.3: Build Your First Reference
**Time:** 5 minutes

Create a quick reference guide for **one** common problem. Choose: overheating diagnostics and fixes, battery problem diagnostics, or "computer won't start" decision tree. Format it so you could use it quickly during a support call. Include: key diagnostic questions, tests to run (in order), most likely causes, quick fixes to try, and when to escalate.

**Save this** — it's the start of your personal troubleshooting library.

**INFO**
**Example — Overheating Quick Reference:** Key questions: fan loud before shutdown? Hot to touch (where)? More often during intensive tasks? When last cleaned? Working surface? Quick tests: 1. Temperature monitor (HWMonitor) — over 85°C confirms. 2. Visual: dust in vents? 3. Pattern: only when hot/intensive work? Quick fixes in order: clean vents externally, use hard flat surface, elevate for airflow, work in cooler time/place, close unnecessary programs. If comfortable: open bottom panel, clean fan/heat sink. Escalate if: continues after cleaning, fan not running at all, still overheats in cool environment on flat surface. Prevention: monthly cleaning, always hard surface, monitor temperatures regularly.

---

## Challenge

### Section 3.4: Complex Scenario Assessments
**Time:** 20 minutes

You've learned the components, the diagnostic process, the patterns, and the decision-making frameworks. Now apply everything to complex, realistic scenarios. **These scenarios will be assessed by your mentor.** Take your time, think systematically, and write detailed responses.

Submit a completed [Consultant's Triage Card](consultant-triage-card.md) with each of the three scenarios below, in addition to your written analysis. All three are drawn from the [scenario bank](05-scenario-bank.md) (C1–C3).

#### Scenario 1: The Cascade of Problems
Lisa in Ethiopia contacts you. Two days ago, there was a severe thunderstorm and power surge. Her laptop was plugged in at the time (no surge protector). After the storm, the computer seemed to work, but the battery icon showed "not charging." She continued working on battery power. Yesterday, the computer shut down (battery depleted), and now when she tries to start it: power light comes on briefly then goes off, she hears a clicking sound from inside, screen remains black, and sometimes she smells a faint burning odour.

**DANGER**
If a user smells burning, they must **immediately stop trying to power it on**. This indicates a short circuit that could lead to a fire. This is not a troubleshooting situation — it is a safety situation. The computer must not be used until IT has inspected it.

Lisa is 4 hours from the nearest city where replacement parts might be available. She has critical translation work due in one week. There's another language worker in a nearby village (2 hours away) who might be able to loan equipment.

**Provide detailed written responses covering:**
1. **Analyse the symptoms:** what components are likely damaged? Explain your reasoning for each.
2. **Assess urgency:** should Lisa attempt further troubleshooting, or pursue other options immediately? Remember the burning smell note.
3. **Recommend an action plan:** what specific steps should Lisa take, considering the time constraint and resource limitations? Put them in order of priority.
4. **Prevention:** after this incident is resolved, what systemic changes would you recommend for Lisa's work setup?

**Submit your complete analysis to your mentor for feedback.**

#### Scenario 2: The Gradual Decline
Robert in Cameroon describes a pattern developing over several months: 3 months ago — occasional unexplained restarts. 2 months ago — programs started crashing more frequently. 1 month ago — display sometimes shows coloured lines or flickers. This week — computer sometimes doesn't recognise his external hard drive when plugged in, and Windows gave an error message about a "hardware malfunction."

The computer still works, but Robert is worried about losing data and wants to know if he should request a replacement or if these issues can be resolved. He works in a hot, dusty environment, backs up weekly but hasn't backed up in 10 days. The nearest IT support is a 6-hour journey. Requesting and receiving a replacement laptop typically takes 2–3 months.

**Provide detailed written responses covering:**
1. **Pattern analysis:** what pattern do you see in these symptoms, and what does it suggest about the underlying problem(s)?
2. **Urgency assessment:** should Robert continue using the computer or take immediate action? Explain your reasoning.
3. **Comprehensive action plan:** outline both immediate actions and long-term solutions. Consider data protection, work continuity, and the 2–3 month replacement timeline.
4. **Decision guidance:** how would you help Robert think through the trade-offs of continuing with this computer versus requesting a replacement?

**Submit your complete analysis to your mentor for feedback.**

#### Scenario 3: The Diagnostic Challenge
Kenji in Nepal says: "My computer is acting strange. Sometimes when I'm typing, the cursor jumps to a different place on the screen, or a window I wasn't clicking on suddenly comes to the front. At first, I thought I was accidentally touching the touchpad, but it happens even when my hands are only on the keyboard. Also, sometimes the computer seems to start doing things on its own — I'll see the cursor moving or programs opening when I'm not touching anything. It's making it really difficult to work."

Kenji works in a mountainous region with temperature extremes (very cold at night, warm during the day). Problems seem worse in the morning when he first starts working. His antivirus software is up to date, and he's careful about what he downloads.

**Provide detailed written responses covering:**
1. **Possible explanations:** this scenario could have hardware OR software causes (or both). What are at least 3 different possible explanations you would consider?
2. **Systematic diagnostic process:** design a step-by-step diagnostic plan to determine the root cause. What would you have Kenji check or try, and in what order? Explain why you've chosen this sequence.
3. **Solution recommendation:** based on what you think is the most likely cause, what solution would you recommend? Be specific.
4. **Contingency plan:** if your initial diagnosis turns out to be wrong, what would be your alternative hypothesis and approach?

**Submit your complete analysis to your mentor for feedback.**

---

## Change

### Section 3.5: Final Reflections and Next Steps
**Time:** 5 minutes

#### Self-Assessment
Before finishing, reflect on your readiness. In your learning journal, note which of these you feel confident about and which you want to strengthen:
- Identify major computer components and their functions
- Distinguish hardware from software problems
- Apply a systematic diagnostic process
- Recognise common hardware failure patterns
- Ask the right questions to gather diagnostic information
- Guide users through basic troubleshooting remotely
- Make appropriate repair/escalate/replace decisions
- Consider environmental factors in diagnosis
- Recommend preventive measures

Write 2–3 specific areas you'll focus on during mentorship.

#### Your Next Steps
**1. Submit your assessment scenarios** (from Section 3.4) to your mentor for review — Scenario 1 (The Cascade of Problems), Scenario 2 (The Gradual Decline), Scenario 3 (The Diagnostic Challenge).

**2. Continue to [Module 4](04-module-4.md).** You now know how to diagnose a fault and decide what to do about it. Module 4 covers the three things that determine whether a laptop survives a field posting at all: the quality of the power feeding it, the safety hazards that end troubleshooting instead of continuing it, and how to keep irreplaceable language data alive when hardware fails.

**3. Then schedule a debrief call** with your mentor to discuss your scenario responses, areas of strength and development, questions from the course, and next steps in your mentorship.

#### Remember
- You don't need to know everything — you need to know how to figure things out systematically
- It's okay to escalate — knowing your limits is professional
- Every case teaches you something — document what you learn
- Prevention is as important as fixing problems
- You're empowering users, not just solving problems

When you're stuck, remember the systematic process: gather more information (observe), consider multiple possibilities (isolate), design a test to distinguish them (test), make a practical recommendation (decide). And when truly stuck: **document what you know and escalate.** That's not failure — that's good professional judgment.

Language workers around the world depend on their computers to do transformative work. When technology breaks, they can't do their jobs. **You're not just fixing computers — you're enabling mission-critical language work.**

### Three Modules Down, One to Go
You've now covered: what's inside a laptop and how components interact, how to systematically diagnose hardware problems, common failure patterns and how to recognise them, how to make practical decisions about repair, escalation and replacement, how to work within resource constraints, and how to build your toolkit.

Module 4 is the field-conditions module — power, safety, and data. It's where the difference between a consultant who has read about remote support and one who can actually keep a rural office running shows up.
