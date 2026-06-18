# Module 2

# **Module 2**

# MODULE 1: UNDERSTANDING NOTEBOOK SYSTEMS
**Duration:** 60 minutes
**Format:** Asynchronous self-paced learning
## Learning Objectives
By the end of this module, you will be able to:
- Identify the major hardware components inside a notebook computer
- Explain how these components interact as a system
- Recognize how environmental factors affect hardware
- Conduct basic information gathering for hardware issues
**Having difficulties with anything?**
Try the discussion assessment, but don't worry it won't be graded.
Or try the [chipp.ai](http://chipp.ai/)
## Section 1.1: Welcome & Course Overview
**Time:** 5 minutes
### Introduction
Welcome to Computer Hardware for Language Technology Consultants! As a language technology consultant, you'll support colleagues working in remote locations around the world. While you won't be repairing hardware yourself, you need to understand how computer hardware works so you can:
- Diagnose whether a problem is hardware or software
- Guide users through basic troubleshooting steps remotely
- Know when to escalate issues to IT specialists
- Recommend appropriate solutions given resource constraints
- Help users prevent hardware problems before they occur
This course focuses on **notebook computers** because that's what most language workers use in the field.
### Course Structure
- **Module 1 (60 min):** Understanding how notebook hardware works
- **Module 2 (60 min):** Diagnosing hardware problems systematically
- **Module 3 (60 min):** Making practical decisions and applying your knowledge
### What You'll Need
- Your own notebook computer to explore
- Internet connection to view resources
- Note-taking materials
- Ability to download and run basic diagnostic software (optional but recommended)
### Assessment
Throughout Module 2 and 3, you'll work through realistic scenarios. Your mentor will review your diagnostic plans and provide feedback.
## Section 1.2: What's Inside a Notebook Computer?
**Time:** 20 minutes
### Introduction
Before you can troubleshoot hardware problems, you need to understand what's actually inside a notebook computer. In this section, you'll take a visual tour inside a typical notebook and learn what each major component does.
### Activity 1.2a: Visual Teardown Tour
**Time:** 10 minutes
We're going to look inside a notebook computer using professional teardown guides. Don't worry - you won't be opening your own computer! But seeing the internal components will help you understand how everything fits together.
### Instructions
1. **Go to **[**iFixit.com**](http://ifixit.com/) - This site has detailed teardown guides for many laptop models
2. **Choose a teardown to view:**
	1. Search for "laptop teardown" or pick any recent notebook model
	2. Good examples: "Dell Latitude teardown" or "HP EliteBook teardown" or "Lenovo ThinkPad teardown"
	3. Choose one with clear photos
3. **As you view the teardown, identify these key components:**
	Take notes on what you see for each component:
	1. **Bottom cover/case**
		1. Protects internal components
		2. Usually has screws and clips
		3. Note: Vents for airflow (look for these!)
	2. **Battery**
		1. Often one of the largest components
		2. May be removable or built-in
		3. Powers computer when unplugged
	3. **Storage drive (SSD or HDD)**
		1. Small rectangular component
		2. SSD: thin, no moving parts, fast
		3. HDD: thicker, has spinning disk inside, slower
		4. Stores all your files and operating system
	4. **RAM (Memory modules)**
		1. Small green circuit boards with chips
		2. Usually 1-2 modules that snap into slots
		3. Temporary storage for running programs
		4. Note: These can become unseated from shock/movement
	5. **Cooling system (fan and heat sink)**
		1. Fan: small propeller that moves air
		2. Heat sink: metal fins or pipes
		3. Draws heat away from CPU/GPU
		4. Note: This is where dust accumulates!
	6. **Motherboard**
		1. Large circuit board everything connects to
		2. Brain of the computer
		3. Has CPU (processor) soldered on
		4. All other components plug into this
	7. **Ports and connections**
		1. USB ports, power jack, display connector, etc.
		2. Connect to motherboard via cables
		3. Can become loose or damaged
	8. **Display assembly** (if shown)
		1. Screen, backlight, cables
		2. Connects to motherboard via ribbon cable
		3. Hinge mechanism
4. **Reflection questions** (write these down):
	1. Which components look most fragile or vulnerable to damage?
	2. Where do you see openings where dust could enter?
	3. Which components could you potentially replace if needed?
	4. What surprises you about how compact everything is?
### Activity 1.2b: Explore Your Own Computer
**Time:** 10 minutes
Now let's gather information about YOUR computer without opening it.
### Instructions for Windows
1. Press Windows key + Pause/Break (or right-click "This PC" → Properties)
2. Or type "System Information" in the search bar
3. Look for:
	1. Processor (CPU) type and speed
	2. Installed RAM amount
	3. System type (64-bit or 32-bit)
4. Check storage:
	1. Open File Explorer
	2. Click "This PC"
	3. Look at your C: drive - how much space total? How much free?
5. Check battery (if laptop):
	1. Click battery icon in system tray
	2. What percentage? What's the status?
### Instructions for Mac
1. Click Apple menu → "About This Mac"
2. Note:
	1. Processor
	2. Memory (RAM)
	3. Storage
3. System Report (click "System Report" button for more detail):
	1. Hardware overview
	2. Battery information (under "Power")
### Write Down
- Your CPU: \\______________\\_
- Your RAM: \\______________\\_
- Your storage type and capacity: \\______________\\_
- Your battery health (if shown): \\______________\\_
### Self-Check
Can you now identify these components in the teardown you looked at? Go back to the iFixit teardown and find the RAM, storage, and battery.
## Section 1.3: How Components Work Together
**Time:** 15 minutes
### The System Perspective
Individual components don't work in isolation - they're a **system**. Understanding how they interact helps you diagnose problems.
### Key Interactions to Understand
### 1. Storage ↔ RAM ↔ CPU (The Work Flow)
Think of it like this:
- **Storage (SSD/HDD)** = Filing cabinet (long-term storage, slower to access)
- **RAM** = Your desk (temporary workspace, fast access)
- **CPU** = Your brain (does the actual work)
When you open a program:
1. Program loads from storage to RAM
2. CPU processes instructions from RAM
3. Results may be saved back to storage
**Why this matters for troubleshooting:**
- Slow performance could be: full storage (filing cabinet overstuffed), insufficient RAM (desk too small), or struggling CPU (brain overworked)
- "Computer is slow" isn't enough information - you need to know WHERE the bottleneck is
### 2. Cooling System ↔ All Components (Temperature Management)
All electronic components generate heat. The cooling system keeps temperatures safe.
**The heat cycle:**
1. CPU/GPU generate most heat (they work hardest)
2. Heat sink conducts heat away from chips
3. Fan blows hot air out through vents
4. Cool air enters through intake vents
**Why this matters for troubleshooting:**
- If cooling fails → components overheat → computer shuts down (protective measure)
- Dust blocks vents → poor cooling → overheating → shutdowns, slowness, crashes
- Many "mysterious" problems are actually heat problems
### 3. Power System ↔ Everything (Energy Flow)
Everything needs power to function:
- **Plugged in:** Power adapter → motherboard → components
- **On battery:** Battery → motherboard → components
- Charging: Power adapter → charging circuitry → battery
**Why this matters for troubleshooting:**
- Power adapter failure can look like battery failure (and vice versa)
- Unstable power damages components over time
- Some problems only happen "on battery" or "plugged in" - this tells you about power system
### Activity 1.3: Connection Challenge
**Time:** 5 minutes
For each symptom below, which component or system interaction is likely involved? (You'll learn more detail in Module 2, but try reasoning through it now)
1. **Computer takes 10 minutes to start up, but runs fine once started**
	1. Think: What happens during startup? What gets loaded from where?
	2. Likely component: \\______________\\_
2. **Computer shuts down suddenly when running video editing software**
	1. Think: What does intensive software cause? What safety system triggers?
	2. Likely system: \\______________\\_
3. **Files are corrupting or programs crash frequently**
	1. Think: Where are files stored? What happens if that component is failing?
	2. Likely component: \\______________\\_
4. **Computer works fine plugged in, but battery dies in 30 minutes**
	1. Think: What's different between plugged in and battery operation?
	2. Likely component: \\______________\\_
### Answers
1. Storage (slow loading from storage to RAM)
2. Cooling system (overheating from intensive work)
3. Storage drive or RAM (data corruption)
4. Battery (failing to hold charge)
**Reflection:** The point isn't to always be right, but to think systematically about how components interact. "Something's wrong" becomes "this specific component or interaction is failing."
## Section 1.4: Environmental Factors
**Time:** 15 minutes
### Why Environment Matters
In an ideal world, computers would operate in climate-controlled, dust-free rooms. Language workers don't have that luxury. Understanding environmental stresses helps you:
- Diagnose problems (heat? dust? power?)
- Recommend preventive measures
- Make appropriate repair vs. replace decisions
### Major Environmental Challenges
### 1. Heat
**Effects on hardware:**
- Accelerates component aging (especially batteries)
- Causes thermal throttling (CPU slows down to reduce heat)
- Can cause solder joints to crack over time
- Thermal expansion/contraction stresses components
**Components most affected:**
- Battery (lifespan cut in half at high temperatures)
- Storage drives (especially HDDs)
- CPU/GPU (throttle performance when hot)
**Signs of heat problems:**
- Unexpected shutdowns (thermal protection)
- Slowness during hot parts of day
- Fan running constantly at high speed
- Hot to touch on bottom/sides
### 2. Dust
**Effects on hardware:**
- Clogs cooling vents (leads to overheating)
- Can cause static discharge
- Accumulates on components
- Can interfere with connections
**Components most affected:**
- Cooling system (fan, heat sink)
- Ports and connections
- Internal components over time
**Signs of dust problems:**
- Overheating symptoms
- Loud fan noise (working harder)
- Visible dust in vents
- Unexplained crashes/instability
### 3. Humidity
**Effects on hardware:**
- Causes corrosion of metal contacts
- Can create condensation (water + electronics = bad)
- Affects display (moisture between layers)
- Degrades adhesives
**Components most affected:**
- Electrical contacts (RAM slots, connectors)
- Display
- Keyboard
- Ports
**Signs of humidity problems:**
- Corrosion visible on ports
- Computer won't start after sitting overnight (condensation)
- Display issues (spots, cloudiness)
- Keys sticking
### 4. Power Instability
**Effects on hardware:**
- Voltage spikes damage power circuitry
- Frequent outages stress power adapter and battery
- Can corrupt data on storage drives
- Shortens component lifespan
**Components most affected:**
- Power adapter (often first to fail)
- Charging circuitry
- Battery
- Motherboard
- Storage drive (data corruption)
**Signs of power problems:**
- Dead power adapters
- Won't charge or charges inconsistently
- File corruption
- Random restarts during power fluctuations
### Activity 1.4a: Environmental Assessment
**Time:** 5 minutes
Think about the language workers you'll support. For each environmental factor, rate how much of a concern it is in typical field locations:
- **Heat:** ☐ Low ☐ Medium ☐ High ☐ Extreme
- **Dust:** ☐ Low ☐ Medium ☐ High ☐ Extreme
- **Humidity:** ☐ Low ☐ Medium ☐ High ☐ Extreme
- **Power instability:** ☐ Low ☐ Medium ☐ High ☐ Extreme
For the factors you rated High or Extreme, these should be your first considerations when diagnosing problems.
### Activity 1.4b: Environmental Troubleshooting Practice
**Time:** 5 minutes
Read this scenario and identify the environmental factor:
**Scenario:**
Sarah works in a coastal area. She reports that sometimes when she arrives at her office in the morning and tries to turn on her computer, the screen stays black even though the power light comes on and she can hear the fan. She says this happens more often after particularly humid nights. Yesterday morning this happened, but by afternoon when she tried again, the computer worked fine.
**Questions:**
1. What environmental factor is likely causing this?
2. Why do symptoms improve by afternoon?
3. What would you advise Sarah to do?
### Answers
1. Humidity/condensation - moisture is interfering with electrical connections (likely RAM or display connection)
2. Computer warms up and moisture evaporates during the day
3. Store computer in sealed container with silica gel packets; allow computer to reach room temperature before turning on; work in driest location possible; if problem persists, may need to reseat RAM
**Key Insight:** Environmental clues often point directly to the cause. "Happens more in morning" + "humid nights" + "improves by afternoon" = condensation/moisture problem.
## Section 1.5: Module 1 Mini-Scenario
**Time:** 5 minutes
Let's apply what you've learned so far.
### Scenario
Miguel in Guatemala contacts you: "My computer is running really slowly now. It used to start up in less than a minute, but now it takes 5-7 minutes. Once it's running, programs work mostly okay, but they take a while to open. What should I do?"
You ask a few questions and learn:
- He's been in location for 18 months
- Area has frequent power outages
- He doesn't have a surge protector
- He primarily uses translation and document software
### Your Task
Before moving to Module 2, write down your initial thoughts:
1. Based on the symptom (slow startup, normal operation after), which component is likely the problem?
2. What questions would you want to ask Miguel to confirm your hypothesis?
3. What environmental factor might be contributing to this problem?
**Don't worry about having the perfect answer** - the goal is to start thinking diagnostically. You'll learn systematic troubleshooting in Module 2.
Save your answers - you'll come back to this scenario with new tools.
## Module 1 Summary
### You've Learned
- ✓ What's inside a notebook computer and how to identify major components
- ✓ How components interact as a system
- ✓ How environmental factors (heat, dust, humidity, power) affect hardware
- ✓ How to gather information about a computer system
### Key Takeaways
- Hardware problems aren't random - they're caused by component failures or environmental stresses
- Understanding the system helps you narrow down possibilities
- Environmental context is diagnostic gold
- Simple information gathering (asking questions, checking system info) is the foundation of good troubleshooting
### Before Module 2
- Make sure you successfully accessed your computer's system information
- Review the iFixit teardown - can you identify each major component?
- Think about the environments your users work in
**Ready for Module 2?**
Now that you understand what's inside and how it works, you'll learn how to systematically diagnose hardware problems.
Would you like me to continue with Module 2 and 3 in markdown format?

