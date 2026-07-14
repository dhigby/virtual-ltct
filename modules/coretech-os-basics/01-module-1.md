# MODULE 1: OS FUNDAMENTALS AND IDENTIFICATION

**Duration:** 60 minutes  
**Format:** Asynchronous self-paced learning

## Learning Objectives

By the end of this module, you will be able to:
- Explain what an operating system does and why it matters for language technology work
- Identify the current operating system environment and when each OS is appropriate
- Locate detailed system information on Windows, macOS, and Linux systems
- Assess system compatibility for language software requirements
- Document system specifications accurately

## Introduction

Welcome to Operating Systems for Language Technology Consultants. As a language technology consultant, you support language workers around the world who depend on their computers to do translation, literacy, and linguistic work. While you won't be installing operating systems for everyone, you need to understand how operating systems work so you can:
- Determine whether problems are OS-related or application-related
- Configure systems properly for language work (especially non-Latin scripts)
- Guide users through OS settings and troubleshooting remotely
- Make informed recommendations about OS choices and upgrades
- Know when to escalate issues to IT specialists
- Keep your own system running smoothly

In the past, OS knowledge was assumed as a prerequisite for language technology training. In practice, many consultants arrived without this foundational knowledge, which created barriers to their effectiveness. This course fills that gap.

**Course Structure:**
- **Module 1 (60 min):** Understanding operating systems and identifying what you're working with
- **Module 2 (60 min):** Installing and configuring systems for language work
- **Module 3 (60 min):** Troubleshooting problems and maintaining systems

**What You'll Need:**
- Your own computer (Windows, macOS, or Linux)
- Internet connection to view resources
- A learning journal (digital or paper) to document your findings and responses
- Administrator access to your computer (for some configuration activities)
- Willingness to explore your own system settings

This course is hands-on — you'll be exploring your actual computer system, not just reading about operating systems in theory. All activities are safe and reversible. Throughout Modules 2 and 3, you'll work through realistic scenarios and document your responses in your learning journal. Your mentor will review your diagnostic thinking and provide feedback. This course builds **Has Knowledge** level competency — you'll continue developing toward **With Assistance** and **Independent** levels through real experience and mentorship.

### Reflecting on Your OS Experience

Before we dive into the content, take a few minutes to think about your existing experience with operating systems. You've been using computers for years — which means you already know more than you might think.

In your learning journal, reflect on these questions:
- What operating system do you use most? Have you used others? What differences have you noticed between them?
- Think of a time a computer problem interrupted your work or someone else's. What happened? Did you know what was causing it? How was it resolved?
- When someone asks you "why isn't my computer working?", what do you usually do first? What do you wish you knew that would help you answer that question better?
- Have you ever had to help someone with a computer that was running a different OS than you're used to? What was challenging about that?

If you're learning with others, take a few minutes to share your answers. You'll likely find that between you, you've already encountered many of the situations this course prepares you for.

**INFO**
There are no right or wrong answers here. The goal is to surface what you already know, identify gaps you're aware of, and connect this course to real situations you've encountered. Keep these reflections in mind as you work through the module — you'll find the content connects to your experience more than you expect.

---

_The six-step diagnostic framework in this section will feel familiar if you've worked through other courses in this program. Gathering information, isolating the problem, testing one thing at a time — these principles appear whether you're diagnosing hardware failures or font display problems. Operating system troubleshooting applies the same disciplined thinking to a different layer of the system: the software that sits between hardware and applications._

## Section 1.2: What Does an Operating System Do?

_Time: 15 minutes_

Before you can troubleshoot OS problems or configure systems effectively, you need to understand what an operating system actually does. Most people use computers daily without thinking about the OS — it's invisible until something goes wrong. Think of your operating system as the **bridge between your computer's hardware and the applications you use**. Without an OS, your hardware is just expensive metal and silicon. Without an OS, applications have no way to access the keyboard, display text on screen, save files, or connect to the internet.

The operating system manages three critical jobs:

### 1. Managing Hardware Resources

- Controls access to the processor (CPU)
- Allocates memory (RAM) to running programs
- Manages storage (reading and writing files to disk)
- Handles input devices (keyboard, mouse, touchscreen)
- Controls output devices (display, speakers, printers)
- Manages network connections

When you click to open a document, the OS finds the file on disk, loads it into memory, tells the processor to run the application, and displays the result on screen. The application doesn't talk directly to the hardware — the OS handles all of that.

### 2. Running and Managing Applications

- Launches programs when you click them
- Keeps multiple programs running simultaneously
- Prevents programs from interfering with each other
- Closes programs properly when you're done
- Handles what happens when programs crash or freeze

When you have a word processor, email client, and web browser all open at once, the OS is juggling their competing demands for processor time, memory, and hardware access.

### 3. Providing the User Interface

- Creates the desktop, windows, menus, and icons you interact with
- Handles file management (folders, copying, deleting)
- Manages user accounts and permissions
- Provides system settings and control panels
- Runs background services (like checking for updates or syncing files)

The "look and feel" of your computer — whether it's Windows, macOS, or Linux — is largely determined by how the OS presents itself to you.

### Why This Matters for Language Technology Work

Understanding these three jobs helps you diagnose problems more effectively:

**Hardware management issues** show up as: computers that won't recognise USB drives or keyboards, display problems, network connectivity failures, printers that won't print.

**Application management issues** show up as: programs that won't launch or crash repeatedly, "not enough memory" errors, slow performance when many programs are running, programs that conflict with each other.

**User interface issues** show up as: can't find files or folders, settings that won't save, language or keyboard input problems, font display issues (critical for language work!).

When a language worker contacts you saying "my computer isn't working," your OS knowledge helps you ask the right questions to narrow down which of these three areas is causing the problem.

### Activity 1.2a: Explore What's Running Right Now

_Time: 8 minutes_

Let's make this concrete by looking at what your operating system is doing right now.

**For Windows users:** Press `Ctrl + Shift + Esc` to open Task Manager. Click "More details" if you see that option. Look at the "Processes" tab.

**For macOS users:** Press `Command + Space` and type "Activity Monitor." Press Enter to open it. Look at the list of processes.

**For Linux users:** Open your system monitor application (Ubuntu: search for "System Monitor"; other distros: may be called "Task Manager" or similar).

You're looking at all the programs and background services your OS is currently managing. Notice how many processes are running (probably dozens), which ones are using CPU, and which are using memory — some you recognise and many you don't (background system services).

**In your learning journal, write:**
1. How many total processes are running on your system right now?
2. What is using the most memory?
3. What is using the most CPU?
4. List 3–4 processes you don't recognise — these are likely OS services working in the background.

_Reflection: This is your OS at work — managing dozens of things simultaneously so you can focus on your actual work. When someone says their computer is "slow," you now have a window into what might be happening behind the scenes._

### Activity 1.2b: The OS as Problem-Solver

_Time: 5 minutes_

**Scenario:** Maria is working on a translation project. She has her translation software open, a web browser with research materials, and a PDF document she's referencing. Suddenly her translation software freezes and won't respond.

**In your learning journal, answer:**
1. Which of the OS's three jobs (hardware management, application management, user interface) is most relevant to this problem?
2. What might the OS need to do to resolve this situation?
3. If the OS can't resolve it automatically, what options might it give Maria?

_Think about: you've probably experienced this yourself — a frozen program. What does your OS do when this happens? How does it let you know? What choices does it give you?_

### Key Takeaways: Section 1.2

- The OS is the essential bridge between hardware and applications
- It manages hardware resources, runs applications, and provides the user interface
- Understanding these roles helps you diagnose where problems originate
- Your OS is constantly working in the background, managing dozens of processes you never see
- When troubleshooting, ask yourself: is this a hardware issue, an application issue, or a user interface issue?

## Section 1.3: The Current OS Environment

_Time: 20 minutes_

Now that you understand what an operating system does, let's look at the operating systems you'll actually encounter in your work. Understanding the strengths, limitations, and typical use cases of each OS helps you make informed recommendations and provide better support.

### Windows

**Current Versions:** Windows 11 (released 2021, current version); Windows 10 (released 2015, still widely used, support until October 2025); older versions (Windows 7, 8, 8.1) are end-of-life and no longer receive security updates.

**Market Position:** Windows remains the dominant OS globally, especially in Africa and the Americas where most language technology work happens. Most language software is developed for Windows first, IT departments are familiar with managing Windows networks, and hardware compatibility is broad.

**Strengths for Language Technology Work:** wide software compatibility, extensive font support and rendering capabilities, good keyboard layout customisation, familiar to most users, strong support for assistive technologies.

**Considerations:** licensing costs, higher hardware requirements (especially Windows 11), regular updates can sometimes cause compatibility issues, requires antivirus and security software.

**When Windows Makes Sense:** organisations with existing Windows infrastructure, users who need specific Windows-only language software, contexts where IT support is Windows-focused, when budget allows for licensing and capable hardware.

### macOS

**Current Versions:** macOS Sonoma (released 2023), macOS Ventura (released 2022), macOS Monterey (released 2021). Apple typically supports the current version plus two previous versions.

**Market Position:** macOS has grown in popularity, especially among younger language workers and in contexts where font and typography work is central. Apple computers come with macOS pre-installed, and the OS is tightly integrated with Apple hardware.

**Strengths for Language Technology Work:** excellent font rendering and typography support, superior handling of complex scripts and right-to-left languages, built-in support for many languages and keyboards, Unix-based system, generally fewer security concerns, long-term hardware reliability.

**Considerations:** only runs on Apple hardware (significantly higher upfront cost), smaller selection of language-specific software, some popular language tools are Windows-only, repair and replacement can be expensive in remote locations.

**When macOS Makes Sense:** work heavily involves complex scripts, fonts, or typography; budget allows for Apple hardware investment; language software needed is macOS-compatible; long-term reliability is prioritised over initial cost.

### Linux

**Common Distributions:** Ubuntu (most popular, user-friendly), Linux Mint (similar to Windows interface), Debian (stable, widely supported), Fedora (cutting-edge features), and many others.

**Market Position:** Linux has traditionally been less common for general language work, but it's growing — it extends the life of older hardware that can't run current Windows, has no licensing costs, and has increasing software compatibility.

**Strengths for Language Technology Work:** free and open source, runs well on older hardware, highly customisable, strong security, good font support (especially in recent versions), active community support, can dual-boot with Windows.

**Considerations:** steeper learning curve for users coming from Windows, some language software doesn't run natively on Linux, may require workarounds (Wine, virtual machines), less familiar to many IT support staff, troubleshooting often requires command-line knowledge.

**When Linux Makes Sense:** extending life of older computers, budget constraints prevent Windows licensing or Mac hardware, users are technically capable or have Linux-savvy support, organisation has commitment to open source software.

### Making OS Recommendations

When asked "What OS should I use?" consider these questions: About the user — current OS and comfort level, language software needed, technical confidence, local IT support. About the context — available hardware, organisational standards, what team members use, internet connectivity. About the work — scripts and languages involved, font/typography needs, OS-dependent software, stability vs. latest features. There's rarely one "right" answer — it depends on weighing these factors for each specific situation.

### Activity 1.3a: Identify Your Operating System

_Time: 7 minutes_

You're going to identify exactly what operating system version you're currently running.

**For Windows:** Click the Start button, type "About" and select "About your PC." Look for the "Windows specifications" section.

**For macOS:** Click the Apple menu (top-left corner) and select "About This Mac." You'll see the macOS version name and number.

**For Linux:** Open a terminal window and type `lsb_release -a` and press Enter (or `cat /etc/os-release`). You'll see your distribution name and version.

**In your learning journal, document:** Operating System (e.g., Windows 11, macOS Sonoma, Ubuntu 22.04), Version Number (e.g., 23H2, 14.1, 22.04.3), Architecture (64-bit or 32-bit), and Build Number if shown.

_Why this matters: "I have Windows" isn't specific enough — Windows 10 and Windows 11 have different capabilities and requirements._

### Activity 1.3b: OS Suitability Scenario

_Time: 8 minutes_

**Scenario:** Pastor John in rural Kenya needs a computer for Bible translation work. He'll be using Paratext and needs to work with Ge'ez script (Ethiopic). His organisation has a donated 7-year-old Dell laptop with Windows 7 installed. The laptop has 4GB RAM and a Core i3 processor. He has limited internet connectivity (checks email weekly when he goes to town). His nephew is tech-savvy and runs Linux on his own computer.

**In your learning journal, consider and write:**
1. **What are Pastor John's options for this computer?** Can he keep using Windows 7 — why or why not? Could he upgrade to Windows 10 or 11 — what would be needed? Would Linux be a viable option — what are the tradeoffs?
2. **What questions would you need to ask to give good advice?** About his software needs, available support, and technical comfort level?
3. **What would you recommend and why?** Consider: security, software compatibility, hardware limitations, support availability, cost.

_There's no single "right" answer here — this is about thinking through the factors that matter in real-world situations._

### Key Takeaways: Section 1.3

- Windows, macOS, and Linux each have distinct strengths and appropriate use cases
- Windows remains dominant but macOS and Linux are viable alternatives in specific contexts
- OS recommendations depend on user needs, hardware, budget, software requirements, and available support
- Knowing exactly what OS version someone is running is essential for troubleshooting and compatibility checking
- The "best" OS is the one that fits the specific context and constraints

## Section 1.4: System Information and Compatibility

_Time: 15 minutes_

Identifying your OS version is just the beginning. You need to know how to gather detailed system information and assess whether a system meets the requirements for specific language software.

### Why System Information Matters

When someone contacts you with a problem or asks if they can run certain software, you need to know: processor type and speed (affects performance), amount of RAM (determines what can run simultaneously), available storage space, graphics capabilities, and current updates installed. You need this to determine if a system meets software requirements, diagnose performance problems, decide if an upgrade is feasible, and plan for new software installations.

### Finding Detailed System Information

#### Windows: System Information Tool

Press `Windows key + R`, type `msinfo32` and press Enter. The System Summary screen shows OS Name and Version, System Manufacturer and Model, Processor details, Installed Physical Memory (RAM), and BIOS Version. The Components section (left menu) shows storage devices, display adapters, network adapters, and USB devices. The Software Environment section shows running tasks, startup programs, and system drivers.

**Quick alternative:** Right-click "This PC" → Properties shows basic information.

#### macOS: System Information

**Basic information:** Click Apple menu → About This Mac. You'll see macOS version, processor, memory, and graphics.

**Detailed information:** From "About This Mac," click "System Report" (or hold Option key, click Apple menu, select "System Information"). This opens the detailed system profiler showing hardware (model, processor, memory, storage, graphics), network, and software sections.

#### Linux: System Information Commands and Tools

**Command-line methods:**
- `uname -a` — kernel and system information
- `lscpu` — CPU information
- `free -h` — memory information
- `df -h` — disk space information
- `lsb_release -a` — distribution information
- `sudo lshw` — comprehensive hardware list

**Graphical tools:** Ubuntu: Settings → About shows basic info. System Monitor shows real-time resource usage. HardInfo (install separately) provides detailed hardware information.

### Understanding System Requirements

**Minimum** means the software will run but may be slow or limited. **Recommended** means the software will run well with good performance.

Common compatibility issues to check: **32-bit vs 64-bit** — most new software requires 64-bit OS; 32-bit systems cannot run 64-bit software. **RAM** — 4GB is often minimum but tight for multitasking; 8GB is comfortable for most language work. **Storage space** — check available space, not just total capacity; the system drive needs breathing room. **OS version specifics** — "Windows 10 or later" means Windows 11 is fine; "macOS 12 or later" means Ventura (13) and Sonoma (14) work.

### Activity 1.4a: Document Your System Specifications

_Time: 8 minutes_

Use the appropriate system information tool for your OS and document the following in your learning journal:
- **Operating System:** full name and version
- **Processor (CPU):** brand and model (e.g., Intel Core i5-8250U), speed (e.g., 1.6 GHz), number of cores
- **Memory (RAM):** total installed (e.g., 8 GB), type if shown (e.g., DDR4)
- **Storage:** total capacity, type (SSD or HDD), available free space
- **Display:** screen resolution, graphics/video card if shown
- **System manufacturer and model** (e.g., Dell Latitude 5400)
- **64-bit or 32-bit architecture**

**TIP**
Take a screenshot of your system information window and save it with your learning journal. A picture is sometimes faster than writing everything down.

### Activity 1.4b: Assess Compatibility

_Time: 5 minutes_

**Scenario:** A language worker wants to install Paratext 9. Here are the actual requirements: Windows 10 (64-bit) or later OR macOS 10.13 or later; 1 GHz processor; 2 GB RAM (4 GB recommended); 1 GB available storage; 1024x768 resolution minimum; .NET Framework 4.8 or later (Windows).

**In your learning journal, answer:**
1. Based on your own system specifications (from Activity 1.4a), can your computer run Paratext 9? Check each requirement and identify any that don't meet minimum or recommended specifications.
2. If your system doesn't meet all requirements, what would need to change? Which component is insufficient — would an upgrade be possible, or would a new computer be needed?
3. For Pastor John's computer from Activity 1.3b (7-year-old Dell, Windows 7, 4GB RAM, Core i3): which requirements does it meet? Which does it fail? What's the main barrier to running Paratext 9?

_Think about: sometimes a system technically meets minimum requirements but wouldn't provide a good user experience. How would you communicate that to a user?_

### Key Takeaways: Section 1.4

- Every operating system has tools to view detailed system specifications
- Key specifications include: OS version, processor, RAM, storage, and display
- Software requirements specify minimum (barely runs) and recommended (runs well) specifications
- 64-bit vs 32-bit architecture is a critical compatibility factor
- Knowing your system specifications helps you assess compatibility before installing software
- System information is essential for troubleshooting and making upgrade decisions

---

## Module 1 Summary

### You've Learned

- **Understanding the OS role:** the OS bridges hardware and applications, manages resources, runs programs, and provides the user interface. OS problems fall into three categories: hardware management, application management, and user interface.
- **The current OS environment:** Windows, macOS, and Linux each have strengths and appropriate use cases. OS recommendations depend on context — user needs, hardware, software requirements, support, and budget. There's no universally "best" OS.
- **System identification and compatibility:** you can locate detailed system specifications on any OS, software requirements specify what's needed to run programs, and your system specifications inform troubleshooting and upgrade decisions.

Module 2 will teach you how to configure systems for language work — installing fonts, setting up input languages, and optimising display settings.

### Key Takeaways

- The OS is the essential bridge between hardware, applications, and the user
- Diagnosing "my computer isn't working" starts with narrowing down hardware, application, or user-interface issues
- Windows, macOS, and Linux each fit different users, budgets, and technical contexts — there's no universal "best" choice
- Knowing your exact OS version and detailed system specifications is essential for troubleshooting, compatibility checks, and upgrade decisions

### Before Module 2

**Review your learning journal:** make sure you've documented your system specifications, review your answers to the scenario questions, and note any questions or uncertainties to discuss with your mentor.

**Optional exploration:** if you want to go deeper before Module 2, explore your system's language and regional settings (you'll work with these in Module 2), your display settings and how they affect text readability, and any system information tools we mentioned that you didn't try yet.

**Resources for Staying Current:**
- **For Windows:** Microsoft's Windows Blog ([blogs.windows.com](https://blogs.windows.com)), Windows Update release information, IT professional communities.
- **For macOS:** Apple's macOS page ([apple.com/macos](https://www.apple.com/macos)), macOS release notes and support pages.
- **For Linux:** distribution-specific websites (Ubuntu, Linux Mint, etc.), Linux news sites like OMG! Ubuntu and It's FOSS, community forums for your specific distribution.
- **For language technology:** SIL's software pages for OS compatibility information, language software vendor support pages.

**You've completed Module 1!** Take a break before continuing to Module 2.
