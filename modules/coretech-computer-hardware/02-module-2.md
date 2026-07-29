# MODULE 2: DIAGNOSING HARDWARE PROBLEMS

**Estimated time:** 85 minutes
**Target Audience:** Trainee Language Technology Consultants
**Format:** Asynchronous self-paced learning

## Learning Objectives
By the end of this module, you will be able to:
- Distinguish between hardware and software problems, using tests that don't depend on the operating system
- Use a systematic diagnostic process
- Recognise common hardware failure patterns, and know how a given laptop actually signals a startup failure
- Guide users through diagnostic steps remotely without damaging their hardware
- Apply diagnostic thinking to realistic scenarios

---

## Connect

In Module 1, you learned what's inside a laptop and how components interact as a system. You also met Miguel — his slow computer is waiting for a proper diagnosis. In this module you'll gain the tools to do that systematically.

Before we start, think back to the Mini-Scenario from Module 1. You wrote down your initial thoughts about Miguel's computer. Take a moment to review what you wrote:
- What did you think was causing his problem?
- How confident were you in that guess?
- What information did you wish you had?

By the end of this module, you'll be able to answer those questions systematically — not by guessing, but by following a diagnostic process. You'll also return to Miguel's scenario with new tools and see how your initial thinking compares.

---

## Content

_The diagnostic approach you'll use in this course — observe carefully, isolate the likely cause, test systematically, then decide on action — is the same underlying thinking you'll find across all courses in this program. Whether you're diagnosing an overheating laptop, a font that won't display, or an operating system problem, the mindset is the same: gather information before you act, and work methodically rather than by guesswork. In this course, we apply that thinking to computer hardware._

### Section 2.1: Hardware vs. Software — Making the Distinction
**Time:** 15 minutes (includes Activity 2.1)

As a consultant, one of your most important skills is quickly determining: "Is this a hardware problem or a software problem?" This determines whether you can help remotely or need to escalate, whether the issue can be fixed or requires replacement, and how urgent the situation is.

Many symptoms can be caused by either hardware or software — slowness, crashes, display problems, file corruption, startup issues. So how do you tell the difference?

#### 1. When Does It Happen?
**Hardware issues:** occur at specific times (when hot, when cold, when moved), happen before OS fully loads, correlate with environmental conditions, progressive (get worse over time).

**Software issues:** happen with specific programs or tasks, occur after OS has loaded, may start after software installation/update, may be intermittent but not environmentally linked.

**Examples:** computer crashes when running specific software = likely software. Computer crashes more often on hot afternoons = likely hardware (cooling).

#### 2. Is It Consistent or Variable?
**Hardware issues:** usually consistent once triggered, follow patterns (always when hot, always after 20 minutes), show physical symptoms (beeps, clicking sounds, smell, heat).

**Software issues:** may be inconsistent or random, sometimes fixed by restart, no physical symptoms.

#### 3. Does It Respond to Software Fixes?
**Hardware issues:** don't improve with software troubleshooting, reinstalling programs doesn't help, system restore doesn't fix it, Safe Mode doesn't change behaviour.

**Software issues:** may improve with updates, reinstalls, or restarts, Safe Mode often behaves differently, specific to certain programs or functions.

#### 4. Does It Happen Outside the Operating System? (the strongest test)

The three questions above are useful heuristics, but they're all inferences. There are two tests
that settle the question much closer to conclusively — and both are **free and need no internet**,
which matters when field bandwidth won't support downloading a diagnostic tool.

**Test 1 — the BIOS/UEFI setup screen.** Ask the user to power on and immediately press the setup
key for their model (commonly F2, F10, F12, Del or Esc — it's usually shown briefly on screen).
This screen runs from firmware, *before any operating system loads at all*. Then ask: does the
problem still happen here?

- Screen is garbled, or won't display, or the machine won't reach setup at all → **hardware**,
  near-conclusively. No OS is running, so no OS can be at fault.
- Setup screen looks perfect and behaves normally → the display, GPU and basic system are alive,
  and suspicion shifts to the OS, drivers or drive.

This is the single most valuable question you can ask a remote user about a startup problem, and
almost nobody thinks to ask it.

**Test 2 — a Linux live USB.** Booting a machine from a Linux USB stick runs a complete, known-good
operating system entirely from the USB, without touching the installed OS or drive.

- Boots and runs cleanly from USB → the hardware is fundamentally fine. Your problem is the
  installed OS, its drivers, or the drive it lives on.
- Same faults appear from USB → **hardware**, and now you know it isn't a software problem no
  matter how much the user has been told to reinstall.

You'll build one of these in [Module 3](03-module-3.md) using Ventoy. It's the most useful single
item in a field consultant's kit, and it's the closest thing this course has to a universal
answer to "is it hardware or software?"

**INFO**
Both tests share a logic worth internalising: **isolate the layer**. If you can make the problem
happen with a different operating system, or with no operating system at all, you have proven it
isn't the operating system. Every good hardware/software test is some version of this move.

### Activity 2.1: Hardware or Software?
**Time:** 5 minutes — for each scenario, determine if it's more likely hardware or software and write down your reasoning.

**A.** Computer freezes only when using video conferencing software. Other programs work fine. Started after recent Windows update.

**B.** Computer makes three beeps when you press power button, screen stays black. Happened suddenly after transporting laptop in backpack.

**C.** Files keep getting corrupted. Multiple programs crash. Computer sometimes makes clicking sounds. Gets worse over time.

**D.** Specific application won't launch — gives error message. All other programs work normally. Computer otherwise functions well.

**E.** Computer shuts down without warning, especially when running multiple programs. Bottom of laptop feels very hot. Fan is very loud. Been getting worse over 3 months.

**F.** Computer boots to a blue screen every time. User has reinstalled Windows twice and it still happens. For each of A–F, also name **one OS-independent test** you would ask for.

**TIP**
Answers: **A** — Software (specific to one program, started after update); test: does it happen from a Linux live USB? **B** — Hardware (startup failure signalled before the OS loads, occurred after physical shock — unseated RAM is the classic cause, but confirm the machine has removable RAM first); test: can it reach the BIOS/UEFI setup screen? **C** — Hardware (clicking = failing hard drive, progressive, affects multiple areas); test: SMART status — and back up before testing anything. **D** — Software (single application issue, error message); test: does the app fail for a different user account? **E** — Hardware (thermal symptoms, progressive, correlates with system load); test: watch temperatures under load. **F** — Hardware, despite looking like software: two clean reinstalls that didn't fix it is strong evidence the fault is underneath the OS; test: memory diagnostic, and a Linux live USB — if Linux also crashes, it's RAM or storage, not Windows.

Key principle: look for physical symptoms, environmental correlations, and whether the problem crosses multiple programs. Hardware problems usually affect the whole system; software problems are often more specific. And when a user tells you they've "already reinstalled everything," treat that as *evidence*, not as a dead end — it's an OS-independence test they've already run for you.

### Section 2.2: The Systematic Diagnostic Process
**Time:** 20 minutes (includes Activity 2.2)

Good consultants don't guess — they follow a systematic process. Here's the framework you'll use for every hardware issue: **OBSERVE → ISOLATE → TEST → DECIDE**.

#### Step 1: Observe (Gather Information)
**About the symptom:** what exactly happens? When did it start? Does it happen every time or intermittently? What were you doing when it happened?

**About patterns:** when does it happen (time of day, specific activities, after certain duration)? When does it NOT happen? Is it getting worse, better, or staying the same?

**About context:** any recent changes (new software, updates, physical incidents)? Any environmental factors (heat, storm, transport)? What's the work environment like?

**About the computer:** how old is it? Any previous problems? When was it last cleaned/maintained?

**Physical symptoms:** any sounds (beeps, clicking, grinding, fan noise)? Any smells (burning, electrical)? Any heat (where specifically)? Any visible damage?

_Why this matters: "slow computer" could be anything, but "slow startup, normal after, clicking sound from left side, started after power outage" strongly suggests a failing hard drive damaged by a power surge._

#### Step 2: Isolate (Narrow Down Possibilities)
**Use symptoms to eliminate possibilities:** no display but computer powers on → NOT power system, likely display or graphics. Slow but no crashes → NOT likely RAM, probably storage or cooling. Happens only on battery → battery or charging system.

**Use timing clues:** during startup → storage or RAM. After 20 minutes of use → cooling system. Immediately after transport → unseated component. Gradually worsening → component degradation.

**Use environmental clues:** more problems in heat → cooling or battery. More problems in morning (cold/humid) → condensation, connections. After power outage → power system, possibly storage corruption.

**Consider interactions:** multiple symptoms might be one failing component affecting others. Example: failing cooling → CPU overheats → throttles → appears slow → crashes.

#### Step 3: Test (Confirm Your Hypothesis)
**Observation tests (no intervention):** monitor temperatures during use, listen for specific sounds, note when problems occur vs. don't occur, watch startup sequence carefully.

**Software tests (information gathering):** run built-in diagnostics (`powercfg /batteryreport`, Windows Memory Diagnostic), read drive health with CrystalDiskInfo, check system temperatures with monitoring software, review event logs for error patterns, test with external monitor (isolates display vs. graphics).

**Simple hardware tests (user can perform):** reach the BIOS/UEFI setup screen, boot from a Linux live USB, disable touchpad and use external mouse, try different power outlet, boot in Safe Mode, remove peripherals.

**The free test everyone forgets — a power drain reset.** For any "won't turn on at all" case, before anything else: unplug the adapter, remove the battery if it's removable, then **hold the power button down for 30 seconds**. Reconnect and try again. This discharges residual power and clears a latched power state, and it resolves a surprising share of dead-laptop cases at zero cost and zero risk. Make it step one of every no-boot sequence.

**DANGER**
**Never run CHKDSK on a drive you suspect is physically failing.** CHKDSK checks the *filesystem* — the index of where files live — not the health of the drive hardware. On a drive that is mechanically failing, `CHKDSK /R` forces an intensive read/write pass across the entire surface. That frequently finishes off a dying drive mid-scan, and it can overwrite data that a professional recovery service could otherwise have retrieved. If you hear clicking, or SMART reports reallocated or pending sectors, the correct order is: **stop using the drive → read SMART → image the whole drive → only then think about repair.** CHKDSK's legitimate job is fixing filesystem corruption on a *healthy* drive, typically after a power cut interrupted a write. That's a real and common need in field conditions — just not this one.

**Reading SMART, specifically.** "Check SMART status" is useless advice without knowing what to look at. In CrystalDiskInfo, the overall health summary (Good / Caution / Bad) is the headline, but the attributes that actually matter are:

- **Reallocated Sectors Count** — sectors that already failed and were swapped for spares. Any non-zero value on a spinning drive deserves attention; a rising value means active, ongoing failure.
- **Current Pending Sector Count** — sectors that are failing right now and haven't been remapped yet. This is the most urgent one. Non-zero means back up immediately.
- **Uncorrectable Sector Count** — data already lost.
- For SSDs: **Percentage Used** / **Wear Leveling Count** (how much write life is consumed) and **Media & Data Integrity Errors**.

A "Caution" summary with a rising pending-sector count is a drive to evacuate today, not next week.

**Guided hardware tests (you walk user through):** reseat RAM, clean vents, check cable connections, let system cool and retry.

_The key: choose tests that will clearly distinguish between your top hypotheses. "If it's X, this test will show Y. If it's Z, this test will show W."_

#### Step 4: Decide (Recommend Action)
Based on your findings, determine the appropriate next step:
- **If clearly diagnosable and fixable:** guide user through fix (cleaning, reseating, settings change) and provide preventive advice.
- **If part replacement needed:** assess urgency, recommend specific part and where to source it, consider workarounds while waiting.
- **If complex or diagnosis uncertain:** escalate to IT support with documentation; explain what you've ruled out (saves them time).
- **If repair isn't practical:** cost/benefit analysis, replacement recommendation, short-term alternatives.

**DANGER**
If diagnosis indicates imminent failure — especially a failing storage drive — the first action is always immediate data backup before anything else. A drive that's clicking or showing SMART errors could fail completely at any moment. Back up first, diagnose second.

**Context matters in decisions:** remote location with no parts = different decision than city with tech support nearby. Critical deadline next week = different urgency than routine maintenance. Expensive repair on old computer = maybe better to replace.

### Activity 2.2: Apply the Framework — Miguel's Scenario
**Time:** 10 minutes

Remember Miguel from Module 1? "My computer is running really slowly now. It used to start up in less than a minute, but now it takes 5–7 minutes. Once it's running, programs work mostly okay, but they take a while to open." Context: 18 months in location, frequent power outages, no surge protector.

Work through each step before reading the sample answers below:
- **STEP 1 — OBSERVE:** what additional questions would you ask Miguel? Write at least 4.
- **STEP 2 — ISOLATE:** based on "slow startup, normal operation after," which component is most likely involved? What are you ruling out?
- **STEP 3 — TEST:** what tests would you have Miguel perform? List at least 2.
- **STEP 4 — DECIDE:** if your hypothesis is correct, what would you recommend? Consider both immediate action and prevention.

**TIP**
**Sample answers — OBSERVE:** Does the computer have an HDD or SSD? Do you hear any clicking or grinding sounds during startup? How full is your hard drive? Have you seen any error messages about disk problems?

**ISOLATE:** Most likely — storage drive (HDD) failing or severely full. Slow startup suggests loading OS from storage is the bottleneck. Normal operation after suggests CPU and RAM are fine. Power outages without surge protection could have damaged the drive over time.

**TEST:** Check drive health by reading **SMART** with CrystalDiskInfo — look at reallocated and pending sector counts (not CHKDSK; if this drive is failing, CHKDSK could finish it off). Check free space. Listen during startup — clicking confirms a failing HDD, though note that an SSD gives no audible warning at all. Check event logs for disk errors.

**DECIDE — if drive is failing:** IMMEDIATE: get the data off NOW — image the drive rather than browsing files, and don't run CHKDSK. SHORT TERM: prepare for drive replacement. PREVENTION: power protection — and note that Miguel's context is *frequent outages*, not lightning, so a surge strip is the wrong purchase here. Repeated abrupt power loss is what corrupted this drive, which points at a line-interactive UPS (see [Module 4](04-module-4.md)). If the drive is just very full: free up space, move files to external storage, and still fix the power. Notice how the systematic process turns "computer is slow" (vague) into "failing hard drive, needs imaging and replacement, and the underlying cause is unprotected power" (specific, actionable).

### Section 2.3: Common Failure Patterns
**Time:** 25 minutes (includes Activity 2.3)

You'll see certain problems again and again. Recognising these patterns helps you diagnose faster and more accurately.

#### Pattern 1: Battery Issues
**Symptoms:** won't charge or charges very slowly, "plugged in, not charging" message, battery drains very quickly, computer won't run on battery at all, battery percentage jumps around.

**Likely causes:** battery degradation (age + heat), power adapter failure, charging port damage, charging circuitry problem.

**Diagnose:** check battery health report (`powercfg /batteryreport` on Windows), try wiggling power cable (loose port?), check adapter temperature (should be warm, not extremely hot), try different outlet, try different adapter if available.

**Common context:** batteries 2+ years old in hot climates, power voltage fluctuations, physical damage to ports.

**Solutions:** replace battery or adapter as appropriate; port repair may need a professional; can often continue working plugged in even with dead battery.

**Prevention:** appropriate power protection, avoid extreme heat, don't leave plugged in 24/7 in very hot environments (most vendors ship a charge-limit setting that enforces this for you — see [Module 4](04-module-4.md)), careful with charging port, and never fit a counterfeit battery.

#### Pattern 2: Overheating and Thermal Shutdown
**Symptoms:** unexpected shutdowns (no warning), shutdowns more common with intensive tasks, very loud fan noise, computer hot to touch (especially bottom/sides), slowness that improves after cooling, more problems during hot parts of day.

**Likely causes:** dust blocking cooling vents, fan failure, dried thermal paste, working in hot environment.

**Diagnose:** check temperature with monitoring software, visual inspection of vents (dust visible?), note correlation with workload and time of day, check if problem improves after cooling.

**WARNING**
Two things people routinely get wrong here.

**A silent fan is usually normal.** Most modern laptops use **zero-RPM idle** — the fan deliberately stops when the machine is cool. "The fan isn't spinning" tells you nothing on its own. To actually test the fan, load the CPU (open several programs, play a video) and watch the RPM reading in HWMonitor. A fan that stays at 0 RPM *while the CPU is above 70°C* has failed. A fan at 0 RPM on an idle machine is working as designed.

**85°C is not the failure point.** Modern mobile CPUs are designed to run to roughly 95–100°C and will **throttle** — deliberately slow themselves down — to stay there. True thermal shutdown is nearer 100–105°C. So read the two symptoms differently:
- **Slow under load, temperatures 85–100°C** → thermal throttling. A real problem worth fixing, but the protection is working.
- **Abrupt power-off** → either it hit the shutdown limit, or the problem isn't thermal at all and you should look at the power system.

Sustained temperatures above 85°C under load mean a thermal problem worth acting on. They do not mean the machine is about to die.

**Common context:** dusty environments (dry season especially), months without cleaning, hot workspaces without AC, working on soft surfaces that block vents.

**Solutions:** clean vents and fan (compressed air or careful brushing), improve workspace ventilation, laptop cooling pad if available, fan replacement if fan has failed.

**Prevention:** monthly cleaning in dusty environments, always use on hard flat surface, keep vents clear, work in coolest available location.

#### Pattern 3: Storage Drive Problems
**Failing drive symptoms:** clicking or grinding sounds, very slow performance (especially startup), files corrupting or disappearing, frequent crashes, "Disk error" messages, computer freezes when accessing files.

**Full drive symptoms:** slow performance, "Disk full" warnings, can't save files, programs won't update or install.

**Likely causes:** HDD mechanical failure (clicking = very bad sign), SSD failure, full drive, corruption from power issues, physical shock damage.

**Diagnose:** listen for clicking (classic failing HDD sound), check drive space, read SMART with CrystalDiskInfo, note if problems correlate with file access.

**INFO**
**SSDs fail differently from hard drives, and more treacherously.** There is no clicking, no grinding — no audible warning of any kind. An SSD typically fails by abruptly going read-only, disappearing from the system entirely, or corrupting data silently. By the time the user notices, there's often no window left to copy anything off.

Three consequences for field work:

- **SMART is your only early warning on an SSD.** Watch Percentage Used / Wear Leveling Count. Check it on a schedule rather than waiting for symptoms.
- **Counterfeit and grossly overstated SSDs and memory cards are common in low-resource markets.** A "1 TB" card for a few dollars is firmware-faked and will corrupt data once you write past its real capacity. For language documentation this is a data-loss event waiting to happen — buy storage from a source you trust, and verify real capacity by writing and reading back a full test load before trusting it with recordings.
- **An unpowered SSD left in a hot place slowly loses data.** SSDs hold data as trapped electrical charge, which leaks away over time — faster the hotter the storage. An archive drive sitting in a hot cupboard for a year or two is not the safe long-term store people assume. For irreplaceable recordings, power archive drives up periodically, keep more than one copy, and don't rely on a single SSD left in a drawer.

**Common context:** HDDs in hot/dusty environments or after physical shock, language workers collecting large audio/video files, power outages causing corruption, older drives (3+ years).

**Solutions:** if failing — backup immediately, replace drive. If full — free space, move files to external storage. If corrupted — attempt repair, prepare for replacement.

**Prevention:** regular backups, monitor drive space (don't let it exceed 80%), power protection appropriate to the site — abrupt power loss mid-write is a leading cause of filesystem corruption, so on a site with frequent outages this means a UPS rather than a surge strip — careful transport, consider SSD when replacing.

#### Pattern 4: Display Issues
**Symptoms:** screen stays black (but computer powers on), flickering or intermittent display, lines or artifacts on screen, very dim display (backlight issue), colours wrong or washed out, spots or clouding.

**Likely causes:** loose display cable, backlight failure, display panel damage, graphics hardware problem, humidity damage.

**Diagnose:** test with external monitor (KEY TEST — if external works, problem is in display assembly; if external also fails, problem is in graphics hardware). Check brightness settings. Gently press around screen bezel (loose connection?). Note if intermittent or constant. Look for physical damage.

**Common context:** physical pressure on closed lid, humidity causing condensation, display cable damage from opening/closing repeatedly, impact or drop.

**Solutions:** if cable loose — reseating (may need professional). If backlight failed — replacement (expensive). If graphics issue — may need motherboard repair. Workaround: external monitor while arranging repair.

**Prevention:** protect laptop during transport, don't put pressure on closed lid, store in dry location, open/close gently.

#### Pattern 5: RAM Issues
**Symptoms:** a startup failure signalled before anything reaches the screen (see the POST signalling note below), computer powers on but no display, blue screen errors, random crashes or freezes, program crashes, often happens after physical shock.

**Likely causes:** RAM not seated properly (became dislodged), RAM module failure, RAM slot corrosion (humidity), incompatible RAM (if recently upgraded).

**Diagnose:** identify how the machine is signalling the failure (below), run a memory diagnostic if the machine boots at all, and — only after confirming the RAM is removable — reseat the modules and try one stick at a time. Note if the problem started after transport or in humid conditions.

**Common context:** rough transport (vibration unseats RAM), humid environments (corrosion), after opening computer for other reasons.

**Solutions:** reseat RAM (remove and reinstall firmly), clean contacts gently if corrosion visible, replace RAM if module has failed.

**Prevention:** careful transport (padded case), humidity control, ensure RAM properly seated after any internal work.

**WARNING**
**"Three beeps means RAM" is a decade out of date, and it will strand you.**

Older computers had a small internal speaker and signalled startup (POST) failures with patterns of beeps. **Most laptops built since roughly 2015 have no speaker at all.** They signal the same failures by **blinking an LED** instead:

| Vendor | How it signals a POST failure |
| --- | --- |
| Dell | Amber/white power-LED blink counts (e.g. 2 amber then 3 white) |
| HP | Blinks the caps-lock or num-lock LED |
| Lenovo | Blinks the power button |
| Apple | No POST codes; use Apple Diagnostics (hold **D** at startup) |

So the right question to a remote user is not "do you hear beeps?" but: **"When you press power, do you hear any pattern of beeps, or see any light blinking in a repeating pattern? Count the pattern exactly and tell me the laptop's exact model."** Then look up that pattern for that model — the codes are manufacturer-specific and a blink count means completely different things on different brands.

If a user reports silence and no blinking, that is not evidence the machine is fine. It may simply have no way to tell you.

**WARNING**
**Check whether the RAM is removable before you ask anyone to reseat it.**

A large and growing share of laptops have **RAM soldered directly to the motherboard** — most thin-and-light models, all Apple Silicon Macs, and many Dell XPS/Latitude and Microsoft Surface machines. There is nothing to reseat, and sending a nervous user through a teardown to find that out is all risk and no benefit.

Confirm serviceability first, using tools this course already points you at:

1. Look up the exact model on **iFixit** — the teardown will show either SODIMM slots or soldered chips.
2. Or run the **Crucial system scanner** on the machine, which reports whether memory is upgradeable.
3. If the RAM turns out to be soldered, unseated RAM is off your hypothesis list entirely — and a POST failure on such a machine goes straight to escalation, because the fix is a motherboard.

The same question applies to storage: many modern laptops have soldered storage too.

#### Pattern 6: Power and Charging Problems
**Symptoms:** computer won't turn on at all, power light flickers or doesn't stay on, computer dies immediately when unplugged, charging light doesn't come on, works only when plugged in, burning smell from adapter.

**Likely causes:** dead power adapter, damaged charging port, charging circuitry failure (motherboard), completely dead battery, power button failure (rare).

**Diagnose:** check adapter (warm? any cable damage?), check charging light when plugged in, try different outlet, wiggle connection (loose port?), smell adapter.

**WARNING**
A burning smell from a power adapter means stop using it immediately. Don't plug it in again — it's a fire risk. Replace it before the computer is used again.

**Common context:** voltage fluctuations/power surges, physical damage to adapter or port, old adapter (cables fray over time).

**Solutions:** replace adapter (most common), port repair (may need professional), battery replacement, motherboard repair (expensive, often not worth it).

**Prevention:** appropriate power protection (ESSENTIAL — and "appropriate" does a lot of work in that sentence; a surge protector alone is the wrong answer for most field sites, which is the subject of [Module 4](04-module-4.md)), unplug during storms, careful cable management, replace adapters at first sign of damage.

**INFO**
One reassuring thing about laptops: the external power brick is a **sacrificial buffer.** It sits between the wall and the motherboard, and when bad power arrives, the brick is designed to be what dies. A dead adapter after a thunderstorm is usually the system working exactly as intended — a $40 part absorbed a hit that would otherwise have reached an $800 motherboard. This is why "the adapter died" is often good news, and why desktops (which have no such buffer) fare worse on bad power.

### Activity 2.3: Pattern Recognition
**Time:** 5 minutes — match each scenario to its most likely pattern and component.

**Scenario A:** Computer worked fine yesterday. Today user transported it 4 hours on rough roads. Now it makes three beeps and screen stays black. (Also: what would you need to confirm before recommending a fix, and what would you ask if the machine made no sound at all?)

**Scenario B:** Over the past month, computer has gotten progressively slower. Now takes 10 minutes to start. User hears clicking sounds. Works in hot, dusty environment.

**Scenario C:** Computer shuts down suddenly after 30–45 minutes of use. Fan is very loud before shutdown. Happens more on hot afternoons. Been getting worse.

**Scenario D:** Computer won't turn on. Charging light doesn't come on when plugged in. User mentions recent thunderstorm. Adapter feels unusually cool (not even warm).

**TIP**
Answers: **A** — Pattern 5 (RAM issues) — a POST failure straight after rough transport points to unseated RAM. But confirm the model has removable RAM before recommending a reseat; if it's soldered, this escalates instead. If the machine made no sound at all, ask whether any LED is blinking in a repeating pattern, and get the exact model so you can look the code up — silence may just mean the machine has no speaker. **B** — Pattern 3 (Storage drive failure) — clicking HDD, progressive slowness; back up before testing anything. **C** — Pattern 2 (Overheating) — thermal shutdown, loud fan, environmental correlation. **D** — Pattern 6 (Power problems) — dead adapter, likely from the surge. Note the adapter being stone cold is the clue: a working adapter under load is warm.

### Section 2.4: Remote Troubleshooting Skills
**Time:** 10 minutes (includes Activity 2.4)

As a language technology consultant, you won't be physically present with the user. You need to gather information through questions, guide users through steps they can perform, assess their comfort level with different tasks, and know when to escalate rather than push beyond user capability.

### Communication Strategies
**1. Ask Clear, Specific Questions**

Vague: "Is it slow?" — Specific: "How long does it take from when you press the power button until you can start working? And once you're working, do programs respond quickly or is there delay?"

Vague: "Does it get hot?" — Specific: "Where exactly does it feel hot — bottom, sides, near the vents? Can you hold your hand there comfortably or is it too hot to touch?"

**2. Give Step-by-Step Instructions**

One step at a time. Very specific — not "check the RAM" but "look for small green circuit boards with chips on them." Check understanding at each step. Use pause points ("before you remove anything, take a photo"). Always include safety warnings ("make sure the computer is powered off and unplugged").

**3. Assess Comfort Level**

Before asking someone to open their computer: "Have you ever opened the back of a laptop before?" "How comfortable would you feel removing a couple of screws and looking inside?" If they're uncertain: "Let's try these external tests first" or "Is there anyone nearby who has done this before who could help?"

**4. Use Visual Aids**

Send photos or diagrams. Reference iFixit teardowns for their specific model. Video calls if bandwidth allows. Ask them to take photos and send them.

**5. Document Everything**

Good documentation tracks patterns across users, smooths escalation to IT, builds organisational knowledge, and prevents repeat questions. Document: symptoms (specific details), tests performed, results, actions taken, recommendations.

### Activity 2.4: Write Remote Instructions
**Time:** 5 minutes

Write step-by-step instructions for guiding a user through checking and cleaning their computer's vents externally. Assume the user has never done this before. Write 5–7 steps clear enough to follow via email or chat. Write your answer in your learning journal before reading the sample answer below.

**TIP**
**Sample Answer:** 1. Power off your computer completely and unplug the power adapter. Let it cool for at least 10 minutes. 2. Find the ventilation slots — usually along the sides or back edge, they look like narrow slits or grilles. Take a photo and send it to me if you're not sure. 3. Look at the vents in good light. Do you see dust or debris inside? Is it blocking the openings? 4. If you see dust: using a soft brush (like a clean paintbrush) or a dry cloth, gently brush away any dust from the outside. Brush AWAY from the openings so you're pulling dust out, not pushing it in. 5. If you have compressed air, read the warning below first — the technique matters. 6. After cleaning, take another photo so I can see the result. 7. Let me know if you noticed a lot of dust buildup — that might explain your overheating issues! Key elements: safety (power off, cool down), specificity, confirmation (photos), clear technique, connection to the problem.

**DANGER**
**Blasting compressed air into a laptop vent damages the machine.** This is the most common well-intentioned mistake in laptop maintenance, and the old advice to "use short bursts into the vents" causes two distinct kinds of harm:

1. **It destroys the fan.** The air spins the fan far beyond the speed it was built for. There is no load on it and nothing limiting it, and the bearing gets wrecked — so you create the grinding, noisy, failing fan you were trying to prevent.
2. **It packs the dust in deeper.** Blowing inward drives dust off the intake grille and into the heat sink fins, where it mats together. Surface dust a brush could have removed becomes a blockage that needs the machine opened up.

**If you use compressed air, do it like this:**

- **Stop the fan from spinning first.** Hold the blade still with a toothpick or a cotton bud through the vent. If you can't reach the fan, don't use air at all — brush instead.
- **Blow against the exhaust direction**, so dust exits the way it came in rather than being pushed further inside.
- Keep the can upright, 15 cm (6 inches) away, in short bursts. Held at an angle, these cans spray liquid propellant onto the electronics.

**When you have no compressed air** — which is common, since cans are often unavailable or expensive in the field — a soft brush and a **manual bulb blower** (the rubber squeeze bulb sold for cleaning camera lenses) do the job safely and reusably. A bulb blower can't over-spin a fan because it can't generate that much pressure. It's a better field tool than a can for exactly that reason.

Do **not** substitute an air-compressor line: workshop compressors deliver far too much pressure and their air carries water and oil.

---

## Challenge

### Section 2.5: Guided Practice Scenarios
**Time:** 15 minutes — for each scenario, apply the four-step framework, identify which failure pattern it matches, and write out your complete diagnostic plan.

For every scenario in this section, also fill out a [Consultant's Triage Card](consultant-triage-card.md). It takes a minute and forces you to check the seven things that are easy to skip under pressure — data status, safety risk, encryption keys, the single fastest isolating test, whether the part is even serviceable, the site's power profile, and how the user keeps working tomorrow.

#### Scenario 1: The Black Screen
James in Papua New Guinea says his computer "won't turn on." When you ask him to describe exactly what happens, he says: "I press the power button and I can hear the fan running, and there's a small light on the front that comes on, but the screen stays completely black." James works in a coastal area with high humidity. This morning he noticed some condensation on his desk.

Work through the diagnostic process and write down your answers before reading the teaching points.
1. **OBSERVE:** what additional questions would you ask?
2. **ISOLATE:** based on symptoms, what components are most likely involved? What can you rule out?
3. **TEST:** what tests would you recommend? Put them in order.
4. **DECIDE:** what would you recommend based on likely causes?

**TIP**
**OBSERVE:** Has this happened before? Did anything unusual happen yesterday? Is there visible moisture on or around the computer? If you shine a flashlight on the screen, do you see anything faint?

**ISOLATE:** Computer powers on (fan runs, power light on) → power system works. Screen stays black → display or graphics or connection issue. Given humidity context, likely moisture interfering with connections. Pattern 4 (display) or Pattern 5 (RAM), complicated by humidity.

**TEST (least to most invasive):** 1. Let it dry — power off, remove battery if possible, let sit several hours in dry location. Do not keep trying to power it on while damp. 2. Power drain reset — hold power 30 seconds while unplugged. 3. Can he reach the BIOS/UEFI setup screen? If the setup screen displays fine, the panel and graphics are alive. 4. External monitor test — definitively separates display from graphics. 5. Any beeps, or any LED blinking in a repeating pattern? Get the exact model and look the code up. 6. Flashlight test — faint image means the backlight failed. 7. Only if the model has removable RAM and he's comfortable: reseat it.

**DECIDE — immediate:** don't keep trying to start it, let it dry completely, try restarting after it's dry. If problem persists: if external monitor works → display assembly issue. If external also fails → try reseating RAM. If RAM reseat doesn't help → IT support. Prevention: sealed container with silica gel, acclimate to room temp before starting. Key insight: solution isn't technical repair, it's letting it dry!

#### Scenario 2: The Intermittent Shutdown
David in Chad contacts you frustrated because his laptop has shut down unexpectedly three times this week. No warning — it just turns off suddenly. Sometimes it works fine for hours after restarting, other times it shuts down again within 30 minutes. It seems to happen more often when he's running his translation software with multiple documents open. It's dry season, dust is a constant problem, his office has no air conditioning, and he's been in location 8 months with no maintenance on the computer.

Apply the diagnostic framework — write your answers before reading the teaching points.
1. **OBSERVE:** what questions would you ask?
2. **ISOLATE:** what's your primary hypothesis? Alternative possibilities?
3. **TEST:** design tests to confirm/rule out your hypothesis.
4. **DECIDE:** recommended solution and if problem persists.

**TIP**
**OBSERVE:** Is the fan very loud before shutdown? Does the bottom/sides feel very hot? Does it happen more in the afternoon? Where do you work — on a desk, lap, or cushion? Can you see dust in the vents?

**ISOLATE:** Primary hypothesis — overheating due to dust accumulation (Pattern 2). Supports: dry season, dust problem, 8 months no maintenance, shutdowns during intensive work. Alternatives: power adapter failure (but would expect more consistent behaviour), RAM issue (but would expect crashes not clean shutdowns).

**TEST:** Temperature check — download HWMonitor or Core Temp, what temperatures right before shutdown? (Sustained CPU temperatures over 85°C confirm a thermal problem.) Fan check — running loudly? Running at all? Visual inspection — dust visible in vents? Pattern test — does shutdown happen more during hot part of day?

**DECIDE — immediate:** clean cooling vents (walk David through external cleaning with brush/compressed air). Improve airflow — use on hard flat surface, elevate slightly, position away from walls. Reduce heat generation — close unnecessary programs, work in coolest part of office. If comfortable opening: internal cleaning of fan and heat sink. If persists: fan might be failing → escalate to IT. Prevention: monthly external vent cleaning, quarterly internal cleaning, always use on proper surface. Key insight: simple maintenance solves many "hardware problems."

#### Practice Scenario 3: Your Independent Work
Now try one completely on your own.

Priya in rural India reports that her laptop battery "isn't charging properly." The charging light comes on when she plugs it in, but the battery percentage doesn't seem to increase. Sometimes it says "plugged in, not charging" in the system tray. The laptop is about 2 years old. Priya's area experiences significant voltage fluctuations. The temperature in her workspace regularly reaches 35–40°C during the day.

**Write out your complete diagnostic plan following the four-step framework. Submit to your mentor:**
1. OBSERVE: questions you'd ask (at least 5)
2. ISOLATE: most likely cause(s) and reasoning
3. TEST: specific diagnostic steps in order
4. DECIDE: recommended solution(s) and prevention

Submit a completed [Consultant's Triage Card](consultant-triage-card.md) alongside your plan — this scenario is Scenario F3 in the [scenario bank](05-scenario-bank.md).

---

## Change

### Before Module 3
- Make sure you've completed Practice Scenario 3 (Priya) and submitted to your mentor
- Review the six failure patterns — can you recognise each one from symptoms alone?
- Practise writing clear, step-by-step instructions with someone you know
- **Optional extra practice:** the [scenario bank](05-scenario-bank.md) contains two further intermediate scenarios for this module — **I2: The Mysterious Beeping** and **I3: The Performance Puzzle**. Work either or both the same way as Practice Scenario 3 and submit to your mentor if you want more feedback before Module 3.

### What You've Learned in Module 2
**Hardware vs. software distinction:** three key questions — when does it happen, is it consistent, does it respond to software fixes — help you classify problems quickly. Two further tests settle it much more firmly, and both are free: can the machine reach the **BIOS/UEFI setup screen**, and does the fault still happen from a **Linux live USB**? Both work by isolating the layer — prove the operating system isn't running and you've proven the operating system isn't at fault.

**What not to do:** never run CHKDSK on a drive you think is physically failing (image it first), never blast compressed air into a vent without stopping the fan, and never ask a user to reseat RAM before confirming the RAM is removable. Each of these turns a diagnosis into a second fault.

**The four-step framework (Observe/Isolate/Test/Decide):** systematic diagnosis turns vague complaints into specific, actionable findings. Good observation often points directly to the cause.

**Six common failure patterns:** battery, overheating, storage, display, RAM, and power problems each have recognisable symptom sets, common contexts, and proven diagnostic approaches. Watch the two traps: a silent fan is normally *correct* (zero-RPM idle), and a silent startup failure may mean the laptop has no speaker and is blinking an LED at you instead.

**Remote troubleshooting:** clear specific questions, step-by-step instructions, assessing user comfort level, visual aids, and documentation are the tools of effective remote support.

Good diagnosis is systematic, not guesswork. Environmental and timing clues are diagnostic gold. Prevention is part of your job — don't just fix, help users avoid future problems.
