# MODULE 4: POWER, SAFETY & DATA PROTECTION IN THE FIELD

**Estimated time:** 70 minutes
**Target Audience:** Trainee Language Technology Consultants
**Format:** Asynchronous self-paced learning

## Learning Objectives
By the end of this module, you will be able to:
- Specify appropriate power protection for a site's actual power profile — surge strip, AVR, or line-interactive UPS
- Account for generator, inverter and grounding conditions when diagnosing and recommending
- Identify and respond to the three field safety hazards that end troubleshooting rather than continuing it
- Protect irreplaceable language data through backup discipline, encryption-key custody, and correct handling of a failing drive

---

## Connect

Think about the last three modules. Almost everything you learned was about responding to a fault that has already happened: a slow computer, a black screen, a drive that clicks.

This module is about the three things that decide whether those faults happen at all, and how bad they are when they do.

Here's the pattern that motivates it. Across the scenarios you've worked, look at what keeps appearing in the *Context* paragraph rather than the symptoms:

- Miguel: frequent power outages, no surge protector
- Priya: significant voltage fluctuations, 35–40°C workspace
- Lisa: thunderstorm, plugged in, no surge protector — now a burning smell
- Sofia: a clicking drive, irreplaceable interviews with elderly speakers, last backup three weeks ago

Four different faults. But the *causes* reduce to two: bad power, and heat. And in Sofia's case the fault isn't really the interesting part at all — the interesting part is that a recoverable hardware problem is about to become the permanent loss of recordings of speakers who may not be alive to record again.

In your learning journal, before you start: **for the users you'll support, what do you actually know about the power they run on?** Not whether they have electricity — whether it's steady, what happens in a storm, whether there's a generator, whether the outlets have an earth pin. If the honest answer is "I have no idea," that's the gap this module closes.

---

## Content

_The four steps still apply, but this module shifts where you apply them. Observing a site's power profile before anything breaks is the same skill as observing a symptom after it does — and it's considerably cheaper._

### Section 4.1: Power Quality — What Actually Kills Laptops
**Time:** 20 minutes (includes Activity 4.1)

In Module 1 you learned that "bad power" is three distinct problems: **surges** (brief high-voltage spikes), **sags or brownouts** (voltage below normal), and **swells** (sustained voltage above normal). This section is about what to do with that.

Start with the fact that reverses most people's instincts:

**DANGER**
**A surge protector does not protect against brownouts, and brownouts are the more common problem.**

This matters because "get a surge protector" is the standard advice, it sounds like it covers bad power generally, and it doesn't. A surge protector is a narrow device that clamps brief spikes. Sustained low or high voltage passes straight through it, untouched, to your power adapter — and sustained low or high voltage is what actually kills adapters and charging circuits on weak grids.

If you take one recommendation away from this course, make it this one: **on a site with unstable voltage, a surge protector alone is the wrong equipment.**

#### The Three Products, and What Each One Actually Does

| Device | Protects against | Doesn't protect against | Roughly |
| --- | --- | --- | --- |
| **Surge protector / surge strip** | Brief voltage spikes | Sags, brownouts, swells, outages | Cheapest |
| **AVR (Automatic Voltage Regulator)** | Sags, brownouts, swells — actively corrects voltage back toward normal; usually includes surge protection | Complete outages | Mid-range |
| **Line-interactive UPS** | All of the above, *plus* rides through outages on battery and gives time for a clean shutdown | — | Most expensive |

Reading the table as a decision:

- **Steady grid, occasional storms** → surge protector is genuinely adequate.
- **Voltage visibly fluctuates** — lights dim and brighten, motors slow, adapters run hot — → **AVR**. This is the most commonly *under*-specified case, and the most common field situation.
- **Frequent outages, and work that can't afford abrupt shutdowns** (or a drive already showing corruption from power cuts) → **line-interactive UPS**. Note that a UPS earns its cost twice: it protects the hardware, and it prevents the mid-write power loss that corrupts filesystems.

A "line-interactive" UPS includes an AVR; a cheap "standby" or "offline" UPS often doesn't, and only kicks in when power fails completely. If the goal is voltage correction, confirm the unit actually regulates.

**WARNING**
**Surge protectors wear out, silently.** The component inside that absorbs spikes (a metal-oxide varistor, or MOV) degrades a little with every hit it takes. After enough surges it stops protecting — while the strip continues to supply power and its little green light continues to glow. Nothing tells the user anything has changed.

In a high-surge area, treat a surge strip as a **consumable with a 12–24 month life**, not a one-time purchase. Better units have an audible alarm or a "protection failed" indicator; the cheapest have nothing at all. When you find a laptop that died on a site that "has a surge protector," ask how old the surge protector is — a five-year-old strip in a lightning-prone area is very likely a plain power strip by now.

#### Generators and Inverters

Many field sites don't run on a grid at all, and this changes the advice.

**Generators** don't produce clean power, particularly small ones. Voltage and frequency swing with load, and when something large starts up — a fridge compressor, a water pump, a welder next door — the generator dips and then overshoots. The most damaging moments are **start-up, shutdown, and changeover** between generator and mains.

Practical guidance:
- Run the laptop **on battery** through generator start-up and shutdown. Unplug the adapter first, work on battery, and reconnect once the generator is running steadily. This costs nothing and avoids the worst transients entirely.
- Don't plug sensitive equipment into a generator that's also running motors, if there's any alternative.
- An AVR between the generator and the laptop is well worth it on a site that runs on generator daily.

**Inverters** (converting battery or solar DC to mains AC) come in two kinds, and the difference is real:
- **Pure sine wave** — produces a clean waveform like the grid. Fine for laptop adapters.
- **Modified sine wave** — a cheap, blocky approximation. Laptop power bricks will usually *run* on it, but they run hotter, often buzz audibly, and fail noticeably earlier.

If a site runs on solar or battery through an inverter, **specifying pure sine is one of the highest-value cheap decisions available.** A buzzing, hot adapter on an inverter is not a faulty adapter — it's the wrong inverter.

#### Grounding — the Invisible One

In much of the world, buildings have two-prong outlets with no earth connection, or an earth pin that isn't actually connected to anything. This has a consequence people rarely connect to computer symptoms.

Laptop power supplies deliberately leak a tiny current to their metal case as part of how they filter interference. With a proper earth, that current goes harmlessly to ground. Without one, it has nowhere to go, and the chassis floats at a voltage relative to you.

Two things follow:

1. **The "tingle."** Users report a faint buzzing or tingling sensation when brushing an aluminium laptop case with the back of a hand, especially with bare feet on a concrete floor. It's usually not dangerous — but it is a reliable *sign of an ungrounded supply*, and worth acting on.
2. **Erratic touchpad behaviour.** This is the part almost nobody knows. A floating chassis interferes with the touchpad, which works by sensing tiny changes in capacitance. The result: a cursor that jumps, phantom clicks, and pointer drift **that looks exactly like a failing touchpad or a malware infection.**

**INFO**
**The free test that distinguishes them.** If a user reports a jumpy or erratic cursor, before anything else: **unplug the adapter and use the laptop on battery for a few minutes.**

- Cursor settles on battery, misbehaves plugged in → it's the power path, not the touchpad. Fix the grounding, or use a properly earthed outlet, or work on battery for critical typing.
- Cursor misbehaves equally on battery and plugged in → now it's worth investigating the touchpad itself, drivers, or malware.

This takes one minute, requires no tools, and can save a user a teardown chasing hardware that was never faulty. It belongs near the top of any erratic-input diagnosis.

#### Counterfeit Chargers

Counterfeit and low-quality "OEM" power adapters are widespread in low-resource markets, and they cause real damage: wrong voltage, unstable output, missing protection circuitry, and occasionally fire. A user whose adapter died and who bought a cheap replacement locally may now be feeding their laptop something worse than bad grid power.

What to tell users:
- **Match voltage, wattage and connector exactly** to what the laptop specifies (it's printed on the original adapter and usually on the laptop's base).
- **Under-wattage is not harmless.** An adapter with too low a wattage rating will run hot, may fail to charge under load, and can shut the laptop down mid-task.
- **Treat an implausibly cheap "genuine" adapter as counterfeit** — because it almost certainly is.
- **USB-C charging is not automatically safe either.** Counterfeit USB-C cables and chargers with faulty negotiation chips can deliver the wrong voltage. Buy USB-C power gear from a reputable source; this is not a place to save a few dollars.

#### Activity 4.1: Specify the Protection
**Time:** 10 minutes — for each site, say which device you'd recommend and why. Write your answers in your learning journal before reading the sample answers.

**Site A:** City office, reliable municipal power, occasional dramatic thunderstorms in the wet season. Six laptops.

**Site B:** Rural office. The lights visibly dim whenever the water pump runs. Power is present most of the day but users say adapters "keep dying" — three in eighteen months.

**Site C:** Village location, no grid. Solar panels charging a battery bank, feeding a cheap inverter bought locally. The laptop adapter buzzes and gets hot.

**Site D:** Regional office, grid power, but outages three or four times a day, usually for a few minutes. Users are losing unsaved work and one machine has developed filesystem corruption.

**TIP**
**Site A — surge protector, and replace it every couple of years.** Steady voltage means an AVR would be spending money on a problem this site doesn't have. Storms are the real risk, so surge protection is well matched — but note the wet-season lightning means the strip is taking hits and is a consumable. Add the habit of unplugging during storms, which no device beats.

**Site B — AVR.** This is the classic under-specified case. Dimming lights when a motor starts is a textbook sag, and three dead adapters in eighteen months is exactly what sustained under-voltage does. A surge protector would not have prevented any of those failures, and if the site already has one, that's the point to make clearly. Also check the adapters they're replacing with — a run of cheap counterfeits could be compounding the problem.

**Site C — pure-sine inverter.** The buzzing hot adapter is diagnostic: that's a modified-sine-wave inverter, and the adapter is telling you so. This is not a laptop fault and no amount of surge or AVR protection addresses it. Replacing the inverter fixes the root cause; anything else treats symptoms. Worth noting the adapter is probably already damaged and may need replacing too.

**Site D — line-interactive UPS.** Frequent short outages are precisely what a UPS is for, and here it solves two problems at once: users stop losing work, and the mid-write power cuts that caused the filesystem corruption stop happening. The corruption is the tell that this isn't only a convenience issue — it's a data-integrity issue. Confirm the unit is line-interactive rather than a cheap standby model, so you get voltage regulation as well.

Notice that in three of the four sites, the answer people usually reach for first — a surge protector — was either insufficient or beside the point.

### Section 4.2: Safety — The Three Stop Signs
**Time:** 15 minutes (includes Activity 4.2)

Everything else in this course teaches you to keep investigating. This section teaches the opposite. There are three findings that **end troubleshooting immediately** — where continuing to diagnose is itself the mistake.

**DANGER**
**The three stop signs. Any one of these means stop, not continue.**

1. **A swollen battery** — the laptop's case is bulging, or the trackpad won't click
2. **A burning or ozone smell, or smoke** — something is shorting internally
3. **Visible damage to a power adapter** — frayed, cracked, exposed wire, scorch marks

In each case the correct next action is to **remove the machine from service**, not to run one more test. None of these is a "monitor it and see" situation.

#### Stop Sign 1: The Swollen Battery

This is the one this course previously never mentioned, and it is the most likely to cause an actual fire.

Lithium-ion batteries degrade faster in heat — which describes most of the environments your users work in. As the chemistry breaks down, the cell generates gas internally and the pack physically expands. A swollen pack is under mechanical stress, its internal separators are compromised, and it can vent, ignite, or rupture. Puncturing one causes a violent reaction that is very difficult to extinguish.

**Signs a remote user can check without any tools:**

- **The trackpad stops clicking**, feels stiff, or sits raised. This is usually the very first sign, because the battery sits directly beneath the trackpad on many laptops and pushes it upward.
- **The bottom cover bulges**, or the laptop **rocks instead of sitting flat** on a table.
- **The keyboard deck lifts** or feels spongy in the middle.
- **The lid no longer closes flush**, or there's a visible gap along a seam.
- On a removable battery: it **won't seat properly**, or won't sit flat on a table when removed.

**What to tell the user, in this order:**

1. **Stop using it and stop charging it now.** Shut down, unplug.
2. **Do not press on it, do not puncture it, do not try to force the cover back down.**
3. If the battery is user-removable and can be taken out **without force**, remove it. If it resists at all, leave it — forcing a swollen pack is how you cause the event you're trying to prevent.
4. **Store it somewhere cool, away from anything flammable**, and outside a building if practical. Not in a drawer with paper, not in a vehicle in the sun.
5. **Never in checked luggage, and never on an aircraft.** This is a hard rule, not a precaution — a damaged lithium battery in a cargo hold is a serious hazard and is prohibited.
6. Arrange proper disposal through IT. Do not put it in general waste.
7. The laptop itself is very often fine and can run on mains power with the battery removed — so this is frequently a battery replacement, not a lost laptop. Say so, because users assume the worst.

**Prevention**, which is the real win here: keep laptops out of sustained heat, don't leave them charging at 100% around the clock in hot rooms, and use the vendor charge limit below.

**INFO**
**The free setting that extends battery life in heat.** Lithium batteries age fastest when held at full charge *and* warm. Most vendors ship a setting that caps charging to protect against exactly this, and almost nobody turns it on:

| Vendor | Setting |
| --- | --- |
| Lenovo | Conservation Mode (Vantage) |
| Dell | Primary AC Use (Dell Power Manager) |
| ASUS | Battery Health Charging — 60% / 80% limit (MyASUS) |
| HP | Battery Health Manager (BIOS setting) |
| Apple | Optimized Battery Charging (on by default) |

For a user who works plugged in at a desk all day in a hot office, enabling this is a zero-cost, zero-risk recommendation that materially delays the swelling you just learned to recognise. Make it part of every setup conversation.

Also: **never fit a counterfeit battery.** Third-party batteries of unknown origin are a leading cause of swelling and thermal events, because they lack the protection circuitry that manages charge and temperature. If a replacement battery is suspiciously cheap, it is not a bargain.

#### Stop Sign 2: Burning Smell or Smoke

A burning, sharp, or ozone-like smell means current is going somewhere it shouldn't. Whether it's the adapter, the charging circuit, or the board, the mechanism is the same: a short, generating heat inside an enclosure full of plastic and a lithium battery.

**Response:** power off, unplug, and stop. Do not power it on again "just to see what happens" — that is the single most common mistake, and it's the one that turns a repairable board into a fire. Escalate to IT with the smell documented explicitly; it changes their triage.

#### Stop Sign 3: A Damaged Adapter

Covered in Module 3, and restated here because it belongs in the same list: a frayed, cracked, or scorched adapter is a fire and shock risk, and "it still works if I hold it just right" means the insulation has already failed. Replace it, don't nurse it.

#### And a Fourth Thing: Liquid

Not a stop sign so much as a clock. What determines the outcome of a spill is what happens in the first minute, so it's worth knowing before you need it:

1. **Power off immediately** — hold the power button; don't wait for a clean shutdown.
2. **Unplug, and remove the battery if removable.**
3. **Do not press the power button to "check if it works."** Powering a wet board is what destroys it.
4. **Invert it** — open, screen-down in a tent shape — so liquid drains out rather than deeper in.
5. **Do not apply heat.** No hairdryers, no ovens, no direct sun. Heat warps components and drives liquid further in.
6. **Let it dry for days, not hours**, ideally with silica gel. Rice does essentially nothing and leaves starch dust.
7. **Anything other than clean water — coffee, juice, salt water, soft drinks — leaves a conductive, corrosive residue** that keeps damaging the board after it dries. These need professional cleaning, and the user should expect corrosion problems weeks later even if it seems fine at first.

Salt water is the worst case, and worth flagging for coastal users specifically: salt makes condensation itself conductive and corrosive, which is why coastal humidity does more damage than the same humidity inland.

#### Activity 4.2: Triage the Safety Reports
**Time:** 5 minutes — for each message, decide: is this a stop sign, and what's your first instruction? Answer before reading below.

**A.** "My trackpad has got really hard to click over the last month. I have to press quite hard now. Otherwise the laptop is fine."

**B.** "My laptop smelled a bit funny yesterday when I plugged it in — kind of sharp. It seems OK today though, I've been using it all morning."

**C.** "The laptop is fine but it wobbles on the desk now, it won't sit flat. Did I bend it?"

**TIP**
**A — Stop sign.** A trackpad that has become progressively harder to click over a month is the classic first symptom of a swelling battery pushing up from beneath. The user has told you "otherwise fine," and they're wrong about the significance. Stop charging, stop using, check for bulging or rocking, arrange a battery replacement.

**B — Stop sign, and urgent despite "it seems OK today."** A sharp smell on plugging in points at the adapter or charging circuit. The fact that it works today means nothing — a short that produced a smell once will produce it again, with more heat. Stop using that adapter immediately, inspect it, replace it, and escalate. "It's fine now" is the reasoning that leads to fires.

**C — Stop sign, same cause as A.** A laptop that won't sit flat has a bulging bottom cover, and the thing under the bottom cover doing the bulging is the battery. The user's own theory (that they bent it) is wrong and harmless-sounding, which is exactly why you need to recognise this from the description. Same response as A.

All three users led with a reassurance — "otherwise fine," "seems OK," "the laptop is fine." Learn to hear those as noise. In each case the detail before the reassurance was the important part.

### Section 4.3: Protecting Irreplaceable Data
**Time:** 15 minutes (includes Activity 4.3)

Hardware is replaceable. A recording of an elderly speaker is not.

That asymmetry should change how you triage. A failing $400 laptop holding the only copy of six months of language documentation is not a $400 problem, and the order of operations changes accordingly: **data comes off before diagnosis continues.**

#### The Rule for a Failing Drive

You met this in Module 2 and it is worth stating once more, because it is the most consequential single technical error the previous version of this course contained:

**DANGER**
**On a drive you suspect is physically failing, do not run CHKDSK. Copy the data off first.**

CHKDSK examines the *filesystem*, not the drive hardware. On a dying drive, `CHKDSK /R` forces an intensive pass over the whole surface — which frequently kills the drive part-way through, and can overwrite data a recovery service could otherwise have retrieved. Every additional spin-up of a clicking drive is a chance it doesn't come back.

The correct order:

1. **Stop using the drive.** Every minute of normal use is risk with no benefit.
2. **Read SMART** (CrystalDiskInfo) to confirm what you're dealing with.
3. **Image the whole drive** — a sector-by-sector copy — rather than copying files one at a time. Imaging reads each area once, in order, and works around bad sectors; browsing folders makes the drive seek back and forth repeatedly over the most fragile thing it owns. On Linux, `ddrescue` is the standard tool and is designed exactly for failing drives.
4. **Work from the image**, not the original, once you have one.
5. **Only then** consider filesystem repair — on the copy.

And know when to stop: **if the data is irreplaceable and the drive is badly failing, amateur recovery attempts can destroy professional recovery's chances.** A recovery lab can often read a drive that won't mount. It cannot un-overwrite what CHKDSK rewrote, and it cannot recover a platter that seized during a fourth attempt at copying files. Sometimes the right advice is "stop touching it and let's talk about the budget for professional recovery."

#### The Thing That Turns a Fixable Fault Into Total Loss

**WARNING**
**If a laptop's drive is encrypted and nobody has the recovery key, the data is gone — even when the hardware is fine.**

Many organisations image laptops with **BitLocker** (Windows) or **FileVault** (macOS) enabled, correctly, because a laptop that travels is a laptop that can be stolen. But encryption means the data is unreadable without the key, and this bites in ordinary repair situations, not just theft:

- Moving the drive to another machine to recover files → needs the key.
- A motherboard replacement, which changes the TPM the key was sealed to → needs the key.
- A firmware update or BIOS reset that clears the TPM → the machine demands the key at next boot, and a user who has never seen it is locked out of a working computer.

Most users do not know whether their disk is encrypted, and have never seen a recovery key. So it goes on the triage card, alongside "is the data backed up?", and it is a question to ask **before** anything invasive:

- **Is this drive encrypted?** (BitLocker on Windows, FileVault on Mac)
- **Where is the recovery key?** Ideally held by the organisation's IT, not only in the user's Microsoft or Apple account — and definitely not only on the encrypted machine.

Confirming key custody before a repair takes two minutes. Discovering the gap after a board swap means a functioning laptop with permanently unreadable data.

#### Backups That Survive Field Conditions

The usual guidance is "keep three copies, on two kinds of media, one of them off-site." That's sound, and here's what it means when the off-site location is a day's travel away and the internet is 2G:

- **The external drive in the same bag as the laptop is not a second copy** in any meaningful sense. The same theft, the same vehicle accident, the same house fire, the same power surge through the same outlet takes both. Physical separation is most of the value.
- **Sync is not backup.** If a cloud folder syncs continuously, a file the user deletes or corrupts is deleted or corrupted everywhere, promptly. Versioned backups, or a periodic copy that isn't automatically overwritten, protect against mistakes as well as hardware.
- **Check that a backup restores.** An unverified backup is a belief, not a copy. Restoring one file occasionally is enough to catch the common failure of a backup that has silently been failing for months.
- **A hot archive drive is not safe long-term storage.** As covered in Module 2, an SSD left unpowered loses data over months to years, and faster when hot. For irreplaceable recordings, keep multiple copies, power archive drives up periodically, and don't rely on a single drive in a cupboard.
- **Prioritise honestly.** When bandwidth allows a few hundred megabytes a week, "back up everything" fails. Identify what is genuinely irreplaceable — original recordings, field notes, unpublished analysis — and get *that* out first. Software can be reinstalled; an interview cannot be re-recorded.

#### Activity 4.3: Audit the Exposure
**Time:** 5 minutes

Pick one user you support, or if you're not yet supporting anyone, use your own setup. Answer these five questions honestly, in your learning journal:

1. If this laptop were stolen tonight, what would be permanently lost?
2. Where is the second copy of that, and is it physically somewhere else?
3. Is the drive encrypted, and if so, who holds the recovery key?
4. When was a restore last tested?
5. What protects it from bad power?

**Any question you can't answer is a finding.** Write down the one you'd fix first, and what it would cost — in money and in someone's time. That list is more valuable to your organisation than any diagnosis you'll make this month, because it's the one that prevents the loss instead of responding to it.

**Then work Scenario P3 (Sofia) from the [scenario bank](05-scenario-bank.md) and submit it to your mentor.** It's the case this whole section exists for: a clicking drive, corrupted files, a three-day deadline, and irreplaceable interviews with elderly speakers whose last backup was three weeks ago. Submit a [Consultant's Triage Card](consultant-triage-card.md) with it. Pay attention to what you do *first* — that's the whole assessment.

---

## Challenge

### Section 4.4: Field Readiness Scenarios
**Time:** 15 minutes

Two scenarios drawing on this module. **Both are assessed by your mentor.** Submit a written analysis and a completed [Consultant's Triage Card](consultant-triage-card.md) for each. Both appear in the [scenario bank](05-scenario-bank.md) as C4 and C5.

#### Scenario C4: The Site Survey
You're asked to advise on equipment for a translation office being set up in a rural location before anyone moves in. What you know: grid power reaches the building but the lights visibly dim when the neighbouring mill runs, there are outages most days lasting from minutes to several hours, the building has two-prong outlets throughout, there's a small diesel generator shared with a clinic, and the wet season brings frequent electrical storms. Four laptops, and the team will be recording and transcribing oral histories.

**Provide detailed written responses covering:**
1. **Power protection:** what specifically would you recommend, and why each item? Where a cheaper option exists, explain what it would fail to protect against.
2. **The grounding problem:** what are the practical consequences of two-prong outlets here, both for user experience and for diagnosis later? What would you tell the team to expect?
3. **Generator practice:** what working habits would you establish on day one?
4. **Data protection:** given the recordings are irreplaceable and the internet is slow, design a backup arrangement that will realistically be followed. Address encryption and key custody.
5. **What would you ask** that isn't answered above, and why does it matter?

#### Scenario C5: The Reassuring User
Amina writes from a hot inland office: "Hi — small thing, no rush. My laptop's been getting really hot the last few months, which I know you said to expect here. The trackpad has gone stiff so I'm using a mouse, no big deal. It's also started sitting a bit unevenly on the desk but I think that's just the desk. The battery only lasts about twenty minutes now so I keep it plugged in all the time, which is fine because I work at my desk anyway. Anyway the actual problem is it's been randomly shutting off, could you help with that?"

**Provide detailed written responses covering:**
1. **Triage:** what is the most urgent thing in this message, and is it the problem Amina asked about? Justify the ordering.
2. **The mechanism:** explain how the heat, the plugged-in-always habit, the stiff trackpad, the uneven sitting and the poor battery life relate to one another. Are these separate problems or one?
3. **Your first three instructions** to Amina, in order, with the reasoning you'd give her.
4. **The random shutdowns:** having handled the urgent item, how would you diagnose these? Note more than one candidate cause and how you'd distinguish them.
5. **Prevention:** what would you change about her setup and habits, and what would you enable on the machine?
6. **Communication:** Amina has framed all of this as minor. How do you convey urgency without alarming her or making her feel foolish for not noticing?

**Submit both analyses to your mentor for feedback.**

---

## Change

### Section 4.5: Course Wrap-Up
**Time:** 5 minutes

#### Self-Assessment
In your learning journal, note which of these you're confident about and which you'd want support with:

- Identify major computer components and their functions
- Distinguish hardware from software problems, including with OS-independent tests
- Apply a systematic diagnostic process
- Recognise the six common failure patterns
- Guide users through basic troubleshooting remotely without causing damage
- Make appropriate repair / escalate / replace decisions
- Specify power protection appropriate to a site's power profile
- Recognise the three stop signs and respond correctly
- Protect irreplaceable data before, during and after a hardware failure

Write 2–3 areas you'll focus on during mentorship.

#### What This Module Changes About Your Practice

Three habits worth carrying out of this course:

1. **Ask about power before anything breaks.** Add it to your intake form. "What's the power like, and what protects the laptop?" takes a minute and predicts a large share of the hardware failures you'll see from that site.
2. **Ask "is there a stop sign?" before you troubleshoot.** Data status and safety risk are the first two lines of the triage card for a reason — they're the two things that make everything after them irrelevant if you get them wrong.
3. **Treat the data as the asset.** The laptop is a tool and tools are replaceable. Your users are creating records that in some cases cannot be created again.

#### Your Next Steps
1. **Submit your scenarios** — C4 and C5 from Section 4.4, plus C1–C3 from Module 3 if you haven't already.
2. **Take the [quiz](07-quiz.md)** — 27 questions covering all four modules, 80% (22/27) to pass.
3. **Schedule a debrief call** with your mentor to discuss your scenario responses, your self-assessment, and where you want support.
4. **Begin your mentorship** — real cases, with your mentor's guidance.

### You've Completed the Hardware Course

Across four modules you've learned what's inside a laptop and how the parts interact, how to diagnose systematically rather than by guesswork, the common failure patterns and how each announces itself, how to decide between repair, escalation and replacement under real constraints, how to specify power protection that matches the actual problem, how to recognise the situations that end troubleshooting, and how to protect data that can't be recreated.

**Remember:**
- You don't need to know everything — you need to know how to figure things out systematically
- It's okay to escalate; knowing your limits is professional
- Prevention is as much your job as repair
- Some findings mean stop, not continue
- You're empowering users, not just fixing machines

When you're stuck: gather more information (observe), consider multiple possibilities (isolate), design a test that distinguishes them (test), make a practical recommendation (decide). And when truly stuck: **document what you know and escalate.** That's not failure — that's good professional judgment.

**Keep learning:** iFixit (teardowns and repair guides), manufacturer support forums (model-specific issues), your organisation's support community. For background: Louis Rossmann (board-level repair — for understanding what's possible, not for doing), Linus Tech Tips and JayzTwoCents (consumer hardware).

**Stay current.** Hardware changes, and this course has already had to correct itself once — beep codes gave way to LED blink patterns, removable RAM gave way to soldered memory. Assume some of what you just learned will age the same way, and check rather than assume.

Language workers around the world depend on their computers to do work that in some cases nobody else will ever do again. **You're not just fixing computers — you're keeping irreplaceable work alive.**

### Course Materials Checklist
**Module 1:** iFixit.com teardown guides, System Information tools (built into Windows and Mac), battery health reports (built into Windows and Mac).

**Module 2 — Diagnostic software:** HWMonitor / Core Temp (temperature), CrystalDiskInfo / DriveDx (drive health and SMART), Windows Memory Diagnostic / MemTest86 (memory), Speccy / CPU-Z (system information). Reference: iFixit, manufacturer support forums, Tom's Hardware, AnandTech, Reddit r/techsupport. Plus the two free tests: BIOS/UEFI setup access, and a Linux live USB.

**Module 3 — Physical kit:** Philips #00 and T5 Torx drivers (pentalobe for Apple), plastic spudger, bulb blower, soft brush, thermal paste, silica gel, spare known-good adapter, Ventoy USB stick, spare USB mouse and keyboard. **Documentation:** intake form, troubleshooting log, escalation form, quick reference guides. **Contacts:** IT support, fellow consultants, regional equipment managers, local hands-on help.

**Module 4 — Power and data:** AVR or line-interactive UPS as the site requires, pure-sine inverter where solar/battery powered, surge strips treated as consumables, vendor battery charge limits enabled, encryption recovery keys held by IT, verified off-site backups of irreplaceable material.
