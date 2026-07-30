# Assessment Quiz

## CoreTech: Computer Hardware — Assessment Quiz (27 questions)

This quiz covers all four modules. You need **80% (22/27) to pass.**

Questions are weighted toward safety, power specification and data protection — not because
those modules are longer, but because those are the answers where being wrong burns a laptop or
loses recordings that cannot be made again.

Several questions ask you to reject a plausible-sounding answer. That's deliberate: some of the
wrong options here are advice that circulates widely and was taught in an earlier version of
this course.

Objectives 11 and 12 (decisions under constraint, and building a toolkit) are only lightly
sampled here — they are assessed properly by the mentor-reviewed scenarios and by the reference
guide you produce in Module 3 §3.3.

### Section 1: Components and System Interactions (Questions 1–3)

**Question 1:** Which internal component is the most common place for dust to accumulate and cause problems?
- A) The RAM modules
- B) The cooling system — fan and heat sink
- C) The motherboard
- D) The storage drive

**Question 2:** A user says their computer takes 10 minutes to start up but runs acceptably once loaded. Using the storage/RAM/CPU relationship, which component is the most likely bottleneck?
- A) The CPU, because startup is processor-intensive
- B) The cooling system, because the machine hasn't warmed up yet
- C) The storage drive, because startup is dominated by loading the OS from storage into RAM
- D) The RAM, because the desk is too small

**Question 3:** A laptop works normally on mains power but the battery dies after 30 minutes. What does the "works plugged in but not on battery" pattern tell you?
- A) The problem is in the power/battery system rather than storage, RAM or cooling
- B) The motherboard is failing
- C) The storage drive is corrupt
- D) Nothing useful — it could be any component

### Section 2: Environmental Factors (Questions 4–6)

**Question 4:** Which statement about dust is accurate?
- A) Dust primarily causes damage through static discharge
- B) Dust is harmless as long as the fan still spins
- C) Dust is mainly a thermal problem — it blocks airflow and insulates components — and becomes a contamination problem when damp
- D) Dust only affects desktop computers

**Question 5:** A user in a coastal area reports that on humid mornings their computer powers on — fan runs, power light on — but the screen stays black. By afternoon it works fine. What is the most likely cause?
- A) A failing display panel
- B) Malware that activates on a schedule
- C) A failing power adapter
- D) Condensation interfering with electrical connections, which evaporates as the machine warms

**Question 6:** Why is salt-laden coastal air more damaging than the same level of humidity inland?
- A) Salt makes condensation conductive and corrosive, so moisture does more damage
- B) Salt air is hotter
- C) Salt blocks cooling vents faster than dust
- D) It isn't — humidity is humidity

### Section 3: Hardware vs. Software (Questions 7–9)

**Question 7:** A user's laptop shows a black screen at startup. Which single question best distinguishes a hardware fault from an operating-system fault?
- A) "Have you tried turning it off and on again?"
- B) "When did you last update Windows?"
- C) "Can you reach the BIOS/UEFI setup screen, and does the problem happen there too?"
- D) "How old is the computer?"

**Question 8:** A machine boots and runs cleanly from a Linux live USB, but crashes constantly from its installed Windows. What does this tell you?
- A) The hardware is faulty and needs replacing
- B) The hardware is fundamentally sound; suspicion moves to the installed OS, its drivers, or the drive it lives on
- C) Nothing — Linux and Windows aren't comparable
- D) The RAM has failed

**Question 9:** A user tells you they have already reinstalled Windows twice and the blue screens continue. How should you treat that information?
- A) As a dead end — if reinstalling didn't fix it, nothing will
- B) As a reason to reinstall a third time more carefully
- C) As irrelevant to the diagnosis
- D) As evidence pointing toward hardware: two clean reinstalls that didn't help is an OS-independence test they have already run for you

### Section 4: The Diagnostic Framework and Failure Patterns (Questions 10–14)

**Question 10:** What are the four steps of the diagnostic framework, in order?
- A) Test → Observe → Decide → Isolate
- B) Observe → Isolate → Test → Decide
- C) Diagnose → Repair → Verify → Document
- D) Isolate → Observe → Decide → Test

**Question 11:** A user reports that the fan on their laptop isn't spinning at all. What should you conclude?
- A) Probably nothing — most modern laptops stop the fan at idle by design, so you must load the CPU and watch RPM before judging
- B) The fan has failed and needs replacement
- C) The machine is about to overheat and must be shut down
- D) The motherboard isn't supplying power to the fan

**Question 12:** A laptop reaches 92°C under sustained heavy load and becomes noticeably slow, but does not shut down. What is happening?
- A) It is about to fail permanently
- B) The temperature reading must be wrong
- C) Thermal throttling — the CPU is deliberately slowing to stay within limits. A real problem worth fixing, but the protection is working
- D) The battery is swelling

**Question 13:** A recent laptop shows a black screen at startup and makes no sound whatsoever. What should you ask?
- A) Nothing more — silence means the machine isn't detecting a fault
- B) Whether the user can hear the hard drive spinning
- C) Whether they have tried a different power outlet only
- D) Whether any LED is blinking in a repeating pattern, plus the exact model, since most laptops built since ~2015 have no speaker and signal POST failures by blinking an LED

**Question 14:** Before asking a remote user to open their laptop and reseat the RAM, what must you confirm first?
- A) That they own a Philips #00 screwdriver
- B) That the warranty has expired
- C) That the model actually has removable RAM in slots, rather than memory soldered to the motherboard
- D) Nothing — reseating RAM is always safe to attempt

### Section 5: Remote Support Without Causing Damage (Questions 15–16)

**Question 15:** What is wrong with telling a user to blast compressed air into their laptop's vents?
- A) It over-spins the fan and can destroy its bearing, and it drives dust deeper into the heat sink fins
- B) Nothing — this is correct practice
- C) Compressed air is too cold for electronics
- D) It only works on desktops

**Question 16:** A user reports their laptop will not power on at all. Which no-cost, no-risk step should come first?
- A) Open the case and reseat the RAM
- B) Reinstall the operating system
- C) Order a replacement motherboard
- D) A power drain reset — unplug, remove the battery if removable, hold the power button for 30 seconds, then retry

### Section 6: Power Protection (Questions 17–19)

**Question 17:** A rural office reports that the lights visibly dim whenever the water pump runs, and they have replaced three power adapters in eighteen months. They already own a surge protector. What do you recommend?
- A) A better surge protector
- B) An AVR (automatic voltage regulator), because the symptoms describe voltage sag, which a surge protector does not address at all
- C) Nothing — three adapters in eighteen months is normal
- D) Replace the laptops

**Question 18:** A site runs on solar panels and a battery bank through a cheap locally bought inverter. The laptop's power adapter buzzes audibly and runs hot. What is the most likely cause and fix?
- A) A faulty adapter; replace the adapter
- B) The solar panels are undersized; add more panels
- C) A modified-sine-wave inverter; replace it with a pure-sine-wave inverter
- D) Normal behaviour for solar power; no action needed

**Question 19:** Which statement about surge protectors is true?
- A) Once installed, a surge protector protects indefinitely
- B) A surge protector also corrects low voltage
- C) Surge protectors are only needed for desktop computers
- D) The protective components degrade with each surge absorbed and can stop protecting while the strip still supplies power and its indicator light still glows — in high-surge areas treat it as a consumable

### Section 7: Grounding (Question 20)

**Question 20:** A user in a building with two-prong, unearthed outlets reports a cursor that jumps around and windows that come to the front on their own. Antivirus scans are clean. What is the cheapest test to run first?
- A) Have them unplug the adapter and work on battery for a few minutes — if the cursor settles, it is the ungrounded power path interfering with the touchpad, not a touchpad fault or malware
- B) Reinstall the operating system
- C) Replace the touchpad
- D) Run three more malware scanners

### Section 8: Safety — The Stop Signs (Questions 21–23)

**Question 21:** A user writes: "My trackpad has got really hard to click over the last month, I have to press quite hard now. Otherwise the laptop is fine." What is your first concern?
- A) A worn-out trackpad mechanism, which is cosmetic
- B) Dust under the trackpad
- C) A swelling battery pushing upward from beneath the trackpad — a stop sign requiring the machine be taken out of service
- D) A driver problem

**Question 22:** Which of these is the correct handling of a laptop with a confirmed swollen battery?
- A) Press the cover back down and keep using it until a replacement arrives
- B) Stop using and charging it, don't press or puncture it, remove the battery only if it comes out without force, store it cool and away from flammables, and never put it in checked luggage or on an aircraft
- C) Discharge it fully by running the laptop until it dies, then dispose of it in general waste
- D) Put it in the freezer to reduce the swelling

**Question 23:** A user says their laptop smelled sharp and burning when plugged in yesterday, but it has worked fine all morning today. What do you do?
- A) Nothing — it is working now
- B) Ask them to keep using it and report if the smell returns
- C) Recommend a full malware scan
- D) Treat it as urgent: stop using that adapter immediately, inspect and replace it, and escalate. A short that produced a smell once will do it again with more heat

### Section 9: Data Protection (Questions 24–25)

**Question 24:** A drive is clicking and the machine holds the only copy of irreplaceable interview recordings. What is the correct order of actions?
- A) Stop using the drive, read SMART, image the entire drive sector-by-sector, then work from the image
- B) Run CHKDSK /R to repair the drive, then copy the files off
- C) Copy the files one folder at a time while continuing to work normally
- D) Defragment the drive first to consolidate the files

**Question 25:** Why does encryption belong on the triage card next to "is the data backed up?"
- A) Because encryption slows the drive down
- B) Because encrypted drives fail more often
- C) Because if the drive is encrypted and nobody holds the recovery key, ordinary repairs — moving the drive to another machine, a motherboard swap, a TPM reset — can leave the data permanently unreadable even though the hardware is fine
- D) It doesn't — encryption is only relevant to theft

### Section 10: Decisions and Toolkit (Questions 26–27)

**Question 26:** A 4-year-old laptop in a dusty office shows overheating shutdowns, a flickering display, intermittent USB ports and a clicking hard drive. The user has a critical translation deadline in two weeks. What is the right recommendation?
- A) Replace the machine — multiple independent failures on an old machine, and a clicking drive means imminent data loss that a two-week deadline can't absorb. Get the data off first, then request replacement, and minimise use meanwhile
- B) Repair each fault in turn, starting with the cheapest
- C) Escalate to IT and wait for their assessment before doing anything
- D) Do nothing until after the deadline, so the user isn't disrupted

**Question 27:** True or False: because replacement parts often take six to eight weeks to reach field locations, a generic field kit — the right screwdrivers, thermal paste, a bulb blower, a spare adapter, a Ventoy USB stick — is more useful to keep stocked near users than model-specific spare parts.
- A) True
- B) False

---

## Answer Key

1. B | 2. C | 3. A | 4. C | 5. D | 6. A | 7. C | 8. B | 9. D | 10. B | 11. A | 12. C | 13. D | 14. C | 15. A | 16. D | 17. B | 18. C | 19. D | 20. A | 21. C | 22. B | 23. D | 24. A | 25. C | 26. A | 27. A

## Objective coverage

| Objective (from `00-design.md`) | Questions |
| --- | --- |
| 1 — Identify components and their function | 1 |
| 2 — Explain system interactions, locate a bottleneck | 2, 3 |
| 3 — Environmental factors as diagnostic evidence | 4, 5, 6 |
| 4 — Hardware vs. software with OS-independent tests | 7, 8, 9 |
| 5 — Apply Observe → Isolate → Test → Decide | 10 |
| 6 — Recognise failure patterns, POST signalling, serviceability | 11, 12, 13, 14 |
| 7 — Guide a remote user without damaging hardware | 15, 16 |
| 8 — The three safety stop signs | 21, 22, 23 |
| 9 — Specify power protection; generator/inverter/grounding | 17, 18, 19, 20 |
| 10 — Protect irreplaceable data | 24, 25 |
| 11 — Repair / escalate / replace under constraints | 26, plus scenarios C1–C3 (Module 3) |
| 12 — Assemble a field toolkit | 27, plus Activity 3.3 (Module 3) |

Objectives 11 and 12 carry only one quiz question each because they are primarily assessed
elsewhere: judgment under constraint is tested properly by the mentor-reviewed complex scenarios,
and "build your own reference guide" is a produced artifact. The quiz questions check that the
underlying principles landed, not that the skill has been demonstrated.
