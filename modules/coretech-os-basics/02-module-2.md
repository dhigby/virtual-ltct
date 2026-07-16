# MODULE 2: INSTALLATION AND CONFIGURATION

**Duration:** 40-45 minutes  
**Format:** Asynchronous self-paced learning

---

## Learning Objectives

By the end of this module, you will be able to:
- Determine when OS installation is necessary and how to prepare users
- Locate and document system information for troubleshooting
- Install fonts (especially SIL fonts) for language work
- Configure basic input languages and keyboard layouts
- Optimize display settings for readability

---

## Section 2.1: Module 2 Introduction

_Time: 3 minutes_

### Welcome to Module 2

In Module 1, you learned what operating systems do and how to identify what you're working with. You explored the three major OS families and located system information. Now you're ready to learn the practical configuration skills that language technology consultants actually need.

### Why These Skills Matter

As a language technology consultant, you'll encounter situations where:

**Users can't work because of configuration issues:**
- "I can't type in my language"
- "The text shows up as boxes"
- "Everything looks blurry and hard to read"

**Systems need preparation or setup:**
- A user needs their OS reinstalled - how do you help them prepare?
- A new computer arrives - what needs to be configured for language work?
- Someone asks "Do I really need to reinstall?" - how do you advise them?

**You need information to help troubleshoot:**
- Remote support requires knowing exactly what system the user has
- Software compatibility questions need specific system details
- Performance problems require understanding the user's setup

This module teaches you the practical skills to handle these situations.

### What You'll Learn

Module 2 focuses on five essential areas:

**Installation fundamentals** - When reinstallation is actually needed (and when it's not), and how to prepare users so the process goes smoothly.

**System information** - How to quickly gather the details you need to troubleshoot problems or check software compatibility.

**Font installation** - How to install SIL fonts (and why they're your best choice) for language work.

**Input languages** - How to configure keyboards for typing in different languages and scripts.

**Display optimization** - How to make text readable and clear, especially for extended language work.

### What This Module Doesn't Cover

To keep this module focused and practical, we're not covering:
- Detailed step-by-step installation processes (IT specialists handle that, or it happens during "With Assistance" phase)
- Custom keyboard layouts (covered in later courses)
- Every possible system setting (only what matters for language work)
- Advanced troubleshooting (that's Module 3)

### Module Structure

- **Section 2.2 (12 min):** Installation fundamentals and gathering system information
- **Section 2.3 (12 min):** Installing fonts for language work
- **Section 2.4 (12 min):** Configuring input languages
- **Section 2.5 (10 min):** Display optimization and wrap-up

### Before You Begin

For the hands-on activities in this module, you'll need:
- Administrator access to your computer (to install fonts and change settings)
- Your learning journal from Module 1
- Internet connection (to download fonts)
- About 25-30 minutes for hands-on activities

All activities are safe and reversible. We'll guide you through each step.

---

## Section 2.2: Installation Fundamentals and System Information

_Time: 12 minutes_

### Part A: When Installation is Necessary

Understanding when operating system installation or reinstallation is actually needed helps you give good advice and prevent unnecessary work.

### When Reinstallation IS Necessary

**You must reinstall when:**
- New computer arrives with no operating system (rare - most come pre-installed)
- Hard drive was replaced (no OS on the new drive)
- Upgrading to completely different OS (Windows to Linux, for example)
- System corruption is so severe the OS won't start at all
- Severe virus or malware infection that can't be cleaned

**Reinstallation might be the best option when:**
- System has become extremely slow despite troubleshooting attempts
- Persistent software conflicts that can't be resolved
- Major OS update failed and caused ongoing problems
- User wants a "fresh start" after years of accumulated software and settings

### When Reinstallation is NOT Necessary

Many problems that seem severe don't actually require reinstallation:

**Don't reinstall for:**
- Minor software problems (uninstall and reinstall that specific program instead)
- Slow performance that hasn't been diagnosed yet (troubleshoot first - Module 3 teaches this)
- Configuration issues like language settings or display problems (that's what you're learning to fix!)
- User account problems (usually fixable without touching the OS)
- "The computer is acting weird" (diagnose first, reinstall last)

**Your value as a consultant:** Knowing when reinstallation is actually needed versus when simpler solutions will work. Reinstallation takes hours, risks data loss if not done carefully, and requires complete reconfiguration afterward. It should be a last resort, not a first response.

### Preparing Users for Installation

When reinstallation truly is necessary, proper preparation makes all the difference between a smooth process and a disaster.

**Your pre-installation checklist for users:**

**Critical - must happen:**
1. **Back up all data** - Documents, photos, email, browser bookmarks, application data
2. **Document what's installed** - List all programs they use, especially language software
3. **Save settings** - Note any custom configurations, especially for language work
4. **Verify license keys** - Find product keys for OS and software before they're inaccessible
5. **Test the backup** - Can you actually restore files from it? Test before wiping the system

**Important - should happen:**
1. **Check system requirements** - Use your Module 1 skills to verify the system can run the OS
2. **Plan for downtime** - Minimum 2-4 hours, possibly longer
3. **Arrange support** - Know who to contact if problems arise during installation
4. **Have installation media ready** - USB drive, download link, product key accessible

**Common backup mistakes that cause data loss:**
- Backing up to the same drive that will be erased
- Not backing up application data (just documents)
- Forgetting email, browser data, custom dictionaries
- Not testing the backup before wiping the system
- Forgetting passwords for email, software, online accounts

### After Installation: Where You Add Value

IT specialists might install the operating system, but **you're the one who makes it usable for language work.** Fresh installations need:
- Language packs and input languages configured
- Fonts installed (SIL fonts for the scripts they work with)
- Display settings optimized for readability
- Language software reinstalled and configured
- User data restored from backup

Modules 2 and 3 teach you these post-installation skills. This is where your specialized knowledge matters most.

### When to Do It Yourself vs. When to Escalate

**You can likely guide a user through reinstallation if:**
- It's straightforward (same OS, same computer)
- User is technically capable
- All prerequisites are met (backup done, license available)
- You can provide remote support if needed

**Escalate to IT specialist if:**
- Hardware problems suspected (diagnose hardware first)
- Complex setup needed (dual-boot, domain joining, RAID)
- User is not confident technically
- Mission-critical system (downtime very costly)
- Licensing questions you can't answer confidently

**Remember:** You're a consultant who understands the process and prepares users well, not someone who must do every technical task personally.

---

### Part B: Gathering System Information for Troubleshooting

Now let's shift to a practical skill you'll use constantly: quickly gathering system information.

### Why You Need System Information

When troubleshooting remotely or advising users, you need more than "I have Windows." You need specifics:

**For troubleshooting:**
- "Is this a hardware limit or software problem?"
- "Why is the system slow?"
- "Is the RAM sufficient for this task?"

**For compatibility:**
- "Can this system run the new version of Paratext?"
- "Will this language software work?"
- "Is an upgrade possible?"

**For making recommendations:**
- "Should we reinstall or just add RAM?"
- "Is this system too old for Windows 11?"
- "What's the bottleneck affecting performance?"

### Quick Access to System Information

Each OS provides tools to view system details. You learned these locations in Module 1, but let's focus on the quick methods you'll use most often.

**Windows - Quick Method:**
1. Press `Windows key + R`
2. Type `msinfo32` and press Enter
3. System Information window opens with all details

**Alternative:** Right-click "This PC" → Properties (shows basic info)

**macOS - Quick Method:**
1. Click Apple menu → "About This Mac"
2. Basic info appears immediately
3. Click "System Report" for detailed information

**Linux - Quick Method (Ubuntu/GNOME):**
1. Settings → About
2. Shows basic system information

**Command line (all Linux):** `lscpu` for CPU, `free -h` for memory, `df -h` for disk

### Activity 2.2: Document Your System Information

_Time: 5 minutes_

You're going to create a quick reference of your system specifications that you can use for troubleshooting throughout this course and in your future work.

**Instructions:**

Use the quick access methods above to find your system information.

**In your learning journal, document these essentials:**
1. **Operating System:**
   - Full name and version (e.g., "Windows 11 Pro version 23H2")
   - 32-bit or 64-bit
2. **Processor:**
   - Brand and model (e.g., "Intel Core i5-8250U")
   - Speed (e.g., "1.6 GHz")
   - Cores (e.g., "4 cores")
3. **Memory (RAM):**
   - Total installed (e.g., "8 GB")
4. **Storage:**
   - Total capacity (e.g., "256 GB SSD")
   - Available free space (e.g., "128 GB free")
5. **Display:**
   - Resolution (e.g., "1920 x 1080")
6. **System Model:**
   - Manufacturer and model (e.g., "Dell Latitude 5400")

**Why document this?**
- You'll reference it in Module 3 troubleshooting exercises
- It's a model for the information you'll need from users
- Helps you practice gathering information quickly
- Creates a baseline for your system's configuration

**Tip:** Take a screenshot of your system information window and save it with your learning journal. A picture is sometimes faster than writing everything down.

---

### Key Takeaways: Section 2.2

- Reinstallation is necessary sometimes, but less often than people think
- Proper preparation (especially backup!) prevents data loss disasters
- System information is essential for troubleshooting and compatibility checking
- Quick access methods let you gather information efficiently
- Your documented system specs become a reference for troubleshooting work

---

## Section 2.3: Installing Fonts for Language Work

_Time: 12 minutes_

### Introduction

One of the most common problems language workers face is text that doesn't display correctly. Often the issue is simple: the right font isn't installed. This section teaches you how to install fonts, with emphasis on SIL fonts which are specifically designed for language work.

### Why Fonts Matter for Language Work

**The problem:**

When a computer doesn't have the right font, characters can:
- Show up as empty boxes: □□□
- Display as question marks: ???
- Appear as generic replacement characters
- Render incorrectly (letters don't connect, diacritics misplaced)

This completely blocks language work. Translators can't read source text. Literacy workers can't create materials. Linguists can't document languages.

**The solution:**

Installing proper fonts - specifically fonts designed for the scripts you're working with.

### SIL Fonts: Your Primary Recommendation

**For language technology work, SIL fonts should be your first choice.** Here's why:

**Designed specifically for language work:**
- Extensive character coverage (including minority languages)
- Excellent rendering quality for complex scripts
- Proper handling of diacritics and combining characters
- Regular updates and maintenance

**Free and accessible:**
- No cost for any use
- Open source license
- Available for Windows, macOS, Linux
- Easy to download and distribute

**Proven and trusted:**
- Used worldwide in language projects
- Technical support available
- Comprehensive documentation
- Compatible with major software

### Key SIL Fonts to Know

You don't need to memorize all SIL fonts, but know these commonly used ones:

**For Latin scripts (including extended Latin):**
- **Charis SIL** - Serif font, excellent for body text, very comprehensive
- **Andika** - Sans-serif, designed for literacy materials, clear letterforms
- **Gentium Plus** - Elegant serif font with extensive language support

**For Arabic script:**
- **Scheherazade New** - Traditional Naskh style, excellent for extended text
- **Harmattan** - West African Warsh style
- **Lateef** - Sindhi style, also good for Urdu

**For Ethiopic script (Amharic, Tigrinya, etc.):**
- **Abyssinica SIL** - Excellent rendering, comprehensive character set

**For Southeast Asian scripts:**
- **Padauk** - Myanmar (Burmese) script
- **Annapurna SIL** - Devanagari (Hindi, Nepali, Sanskrit)
- **Dai Banna SIL** - New Tai Lue script

**For other scripts:**
- **Alkalami** - Kano region Arabic style
- **Awami Nastaliq** - Nastaliq style (Urdu, Persian)

**Where to find them:** software.sil.org/fonts

Each font has its own page with download links, documentation, and character charts.

### Understanding Font Formats

Fonts come in different formats. Here's what you need to know:

**TrueType (.ttf)**
- Widely supported
- Works on all platforms
- Single file per font style

**OpenType (.otf)**
- Modern format
- Better for complex scripts
- Supports advanced typography features
- Most SIL fonts use this format

**Font families:**
- Regular, Bold, Italic, Bold Italic
- Download the complete family
- Applications need all styles for full functionality

### How to Install Fonts

Installation is straightforward but differs slightly by platform.

**Windows:**

**Method 1 (Easiest):**
1. Download the font file (usually a .zip file)
2. Extract the .zip file
3. Right-click on the .ttf or .otf file
4. Click "Install" or "Install for all users"
5. Font installs immediately

**Method 2:**
1. Download and extract font files
2. Copy the font files
3. Open Fonts folder: Settings → Personalization → Fonts
4. Drag font files into the Fonts window
5. Fonts install automatically

**macOS:**
1. Download the font file
2. Double-click the .ttf or .otf file
3. Font Book application opens showing the font
4. Click "Install Font" button
5. Font installs and is immediately available

**Linux (Ubuntu/GNOME):**
1. Download the font file
2. Double-click the .ttf or .otf file
3. Font Viewer opens
4. Click "Install" button (top right)
5. Font installs for current user

**Alternative (all Linux):**
1. Create fonts directory: `mkdir -p ~/.local/share/fonts`
2. Copy font files to that directory
3. Run: `fc-cache -f -v`
4. Fonts are now available

### Activity 2.3a: Install a SIL Font

_Time: 7 minutes_

Now you'll install a SIL font relevant to your language work.

**Part 1: Choose and Download**

Instructions:
1. Go to software.sil.org/fonts
2. Browse the available fonts
3. Choose one font relevant to your work (or one you're curious about)
   - If unsure, choose **Charis SIL** (excellent general-purpose font)
   - Or choose a font for a script you work with
4. Click on the font name to go to its page
5. Click the "Download" button
6. Save the .zip file

**Part 2: Install the Font**

Instructions:
1. Extract/unzip the downloaded file
2. Look inside the extracted folder - you'll see .ttf or .otf files
3. Follow the installation method for your platform (above)
4. Install all the font files in the family (Regular, Bold, Italic, Bold Italic)

**Part 3: Test the Font**

Instructions:
1. Open a text editor or word processor
2. Type some text in English first
3. Select the text and change the font to the one you just installed
4. Observe: Does it look different from your default font?
5. If the font supports other scripts, type or paste text in that script
6. Does the text display correctly?

**In your learning journal, document:**
1. **What font did you install?**
2. **Did installation work smoothly?** (Note any problems)
3. **What script/language is this font designed for?**
4. **Did text display correctly when you tested it?**
5. **How does this font compare to default system fonts for this script?**

**If you encountered problems:**
- Document what went wrong
- Did you get an error message? What did it say?
- Is the font showing up in your application's font list?
- Try restarting the application (sometimes needed for fonts to appear)

---

### Troubleshooting Font Problems

**Problem: Font installed but doesn't appear in application**

Solutions:
- Restart the application (close completely and reopen)
- Check you installed for "all users" not just current user (Windows)
- Verify font files are actually in the fonts folder
- Some applications cache font lists - may need to restart computer

**Problem: Characters still show as boxes after installing font**

Solutions:
- Make sure you actually selected the new font (didn't just install it)
- Check if the font includes those specific characters (view character map)
- Try a different font family (maybe this one doesn't support that script)
- Verify the text encoding is correct (UTF-8 usually)

**Problem: Font looks wrong or text doesn't connect properly (Arabic, etc.)**

Solutions:
- Verify you're using an OpenType font (better for complex scripts)
- Use a different application (some don't support complex text layout well)
- Make sure you installed all font styles (not just Regular)
- Try a different SIL font for that script

**Problem: Downloaded font file won't open or install**

Solutions:
- Verify download completed (check file size)
- Try downloading again (file might be corrupted)
- Make sure you extracted the .zip file first
- Check if you have administrator rights (needed for installation)

### Teaching Users About Fonts

When you help users with fonts, they need to understand:

**What fonts do:**
- Make characters visible and readable
- Different fonts for different scripts
- Not just about appearance - about functionality

**When they need new fonts:**
- Characters showing as boxes
- Text from another language doesn't display
- Better rendering quality needed

**How to install fonts:**
- Show them once, have them do it once
- Create a simple guide with screenshots
- Emphasize: this is a one-time task per font

**Where to get fonts:**
- SIL fonts: software.sil.org/fonts
- Emphasize these are free and safe
- Caution about random internet downloads (stick to trusted sources)

### Best Practices for Font Management

**For consultants:**
- Keep a collection of commonly needed SIL fonts on a USB drive
- Know which fonts work best for the languages in your region
- Test fonts before recommending them to users
- Document which fonts you've installed on each system you support

**For users:**
- Install fonts as needed, not everything "just in case"
- Use SIL fonts for language work (reliable, well-supported)
- Keep font files backed up (in case of reinstallation)
- Don't delete fonts you don't recognize (might be needed by system or applications)

---

### Key Takeaways: Section 2.3

- SIL fonts are the primary recommendation for language technology work
- Fonts must support the specific scripts and characters you're working with
- Installation is straightforward: download, extract, install
- Testing confirms fonts work correctly for your needs
- Common font problems have simple solutions
- Keep commonly needed fonts accessible for quick installation

---

## Section 2.4: Configuring Input Languages

_Time: 12 minutes_

### Introduction

Installing fonts lets you see languages. Configuring input languages lets you type them. This section teaches you how to add keyboard layouts so users can actually input the languages they work with.

### Understanding Input Languages

**What input languages do:**

Input languages (also called keyboard layouts or input methods) determine what happens when you press keys on your keyboard.

**Example:**
- With English (US) input: pressing A types "a"
- With Russian input: pressing A types "ф"
- With Arabic input: pressing A types "ش"
- With Amharic input: pressing "s" then "a" types "ሰ"

The same physical keyboard can input completely different scripts depending on which input language is active.

### Input Language vs. Display Language

It's important to understand the difference:

**Display language (UI language):**
- Language of menus, buttons, error messages
- System-wide setting
- Usually requires downloading language packs
- Example: Seeing "File" vs "Fichier" in menus

**Input language (keyboard layout):**
- What you type when you press keys
- Multiple input languages can be active simultaneously
- Switch between them while working
- Example: Typing in English, then switching to type in Amharic

**You can mix these:** Display in English (comfortable for menus) while typing in multiple other languages. This is common for language workers.

### How Input Switching Works

**Key concepts:**

**Multiple input languages installed:**
- You can have many keyboard layouts ready
- Only one is active at any time
- Switch between them quickly

**Visual indicator:**
- System shows which input is currently active
- Windows: Language code in taskbar (ENG, AMH, ARA, etc.)
- macOS: Flag or language icon in menu bar
- Linux: Indicator in top panel

**Switching methods:**
- Keyboard shortcut (fastest)
  - Windows: Windows key + Space (or Alt + Shift)
  - macOS: Control + Space (or Command + Space)
  - Linux: Super + Space (varies by desktop)
- Click the indicator and choose from list

**Why this matters:** Language workers constantly switch between languages - typing English emails, then translating in another language, then back to English for notes. Smooth switching is essential for efficient work.

### Platform-Specific Configuration

**Windows Language Settings:**
1. Settings → Time & Language → Language & Region
2. Click "Add a language"
3. Search for your language
4. Click Next and Install
5. Language appears in your language list
6. To switch: Windows key + Space

**macOS Language Settings:**
1. System Settings → Keyboard → Input Sources
2. Click the "+" button
3. Find your language in the left panel
4. Select keyboard layout (usually one main option)
5. Click "Add"
6. Tip: Check "Show input menu in menu bar"
7. To switch: Control + Space, or click menu bar icon

**Linux Settings (Ubuntu/GNOME):**
1. Settings → Keyboard
2. Input Sources section
3. Click "+" button
4. Find and select your language
5. Choose keyboard layout
6. Click "Add"
7. To switch: Super + Space

### Activity 2.4a: Add an Input Language

_Time: 7 minutes_

You're going to add a keyboard layout for a language you work with or support.

**Part 1: Choose Your Language**

Instructions:

Think about the language work you do or support. Choose a language that:
- You actually work with (or users you support work with)
- Uses a different script than English (more useful for learning)
- You can test (even if you don't speak it fluently, you can verify input works)

Good choices: Amharic, Arabic, Hindi, Thai, Greek, Russian, Korean, Chinese, French

**Part 2: Add the Input Language**

Instructions:

Follow the steps for your platform (above) to add your chosen language.

In your learning journal, note:
- What language are you adding?
- Any difficulties during the process?

**Part 3: Test Input Switching**

Instructions:
1. Open a text editor (Notepad, TextEdit, gedit, or any word processor)
2. Type a few words in English
3. Use the keyboard shortcut to switch input languages
   - Watch the indicator change
4. Try typing in the new language
   - The keys will produce different characters
5. Switch back to English
6. Type a few more English words to confirm switching works

**In your learning journal, document:**
1. **What language did you add?**
2. **Can you switch between English and the new language?**
3. **What keyboard shortcut switches on your system?**
4. **Where is the input language indicator?**
5. **Try typing a few characters - does input work as expected?**

**Observations to note:**
- Is the keyboard layout intuitive or confusing?
- Can you see the characters you're typing (font issue if not)?
- How would you explain this process to a user?

---

### Teaching Users to Manage Input Languages

Users need practical skills to work effectively with multiple input languages.

**Essential skills to teach:**

**1. How to switch input languages**
- Show the keyboard shortcut (have them practice)
- Show the visual indicator
- Practice switching back and forth several times

**2. How to know which input is active**
- Point out the indicator location
- Explain why checking matters (avoid typing in wrong script)
- Show what happens if you type in the wrong input language

**3. When to add more languages**
- Before starting work in a new language
- Better to set up ahead of time than mid-task

**4. What can go wrong and how to fix it**
- Accidentally switched input (can't type normally) → switch back to English
- Removed a language by accident → re-add it
- Characters not displaying → font issue, not input issue

### Common User Problems

**Problem: "I'm typing but nothing makes sense"**
- Likely typing in wrong input language
- Solution: Check indicator, switch to correct language
- Prevention: Always check indicator before typing

**Problem: "I can't type certain characters I need"**
- Some keyboard layouts don't include all characters
- Solution: Learn the key combinations, or use a different keyboard layout
- Note: Custom keyboards (covered in later courses) can solve this

**Problem: "I accidentally removed my main language"**
- Now can't type at all!
- Solution: Use on-screen keyboard to re-add the language
- Prevention: Be careful when managing input languages

**Problem: "The keyboard layout is confusing"**
- Different languages have different keyboard arrangements
- Solution: Print a keyboard layout chart, practice
- Many layouts follow logical patterns once learned

### Input Languages and Custom Keyboards

**Note:** This course covers standard keyboard layouts provided by operating systems. Custom keyboard layouts (created specifically for languages with unique needs) are covered in later courses in the Language Technology Consultant training.

**Standard layouts work well for:**
- Major languages with OS support
- Languages with established keyboard standards
- Initial setup and basic needs

**Custom keyboards are needed for:**
- Minority languages without OS support
- Languages requiring special character combinations
- Optimized layouts for specific linguistic needs

As you encounter languages that need custom keyboards, you'll learn those tools later in your training.

---

### Key Takeaways: Section 2.4

- Input languages determine what you type, separate from display language
- Multiple input languages can be installed and switched between easily
- Visual indicators show which input is currently active
- Keyboard shortcuts enable quick switching
- Users need practice and clear instructions for managing input languages
- Standard keyboard layouts work for many languages; custom keyboards (later course) handle others

---

## Section 2.5: Display Optimization and Wrap-Up

_Time: 10 minutes_

### Introduction

You've configured fonts and input languages. Now let's optimize how text displays on screen. Proper display settings reduce eye strain and ensure text is clear and readable, especially important for language workers who spend hours reading and analyzing text.

### Why Display Settings Matter

**For any computer user:**
- Reduces eye strain during extended use
- Improves productivity (faster reading)
- More comfortable work experience

**For language work specifically:**
- Complex scripts require clear rendering
- Diacritics and combining marks must be visible
- Small details matter for linguistic analysis
- Extended reading sessions are common

Poor display settings make language work difficult or impossible.

### Key Display Settings

**Display scaling (size):**
- Makes everything on screen larger or smaller
- 100% = native size
- 125%, 150%, 175% = progressively larger
- Choose based on monitor size, your eyesight, and comfort

**Font rendering (clarity):**
- How the OS draws text on screen
- Affects sharpness and readability
- Can be optimized for your specific monitor
- Windows: ClearType technology
- macOS: Automatic, generally excellent
- Linux: Configurable font hinting and anti-aliasing

**Resolution:**
- Native resolution is usually best (sharpest)
- Lower resolution makes things bigger but blurrier
- Scaling is better than changing resolution

### Platform-Specific Display Settings

**Windows Display Settings:**

Basic settings:
1. Settings → System → Display
2. Scale: Choose 100%, 125%, 150%, etc.
3. Resolution: Use recommended (native) resolution
4. Requires sign-out to fully apply

ClearType tuning (important for text clarity):
1. Search "ClearType" in Start menu
2. Run "Adjust ClearType text"
3. Ensure ClearType is turned on
4. Follow wizard, choosing clearest text samples
5. Makes text noticeably sharper

**macOS Display Settings:**
1. System Settings → Displays
2. Resolution: Default recommended (or Scaled for options)
3. Brightness: Adjust for comfort
4. True Tone: Adjusts colors based on ambient light (if available)
5. Night Shift: Reduces blue light in evening

Text rendering is automatic and generally excellent, especially on Retina displays.

**Linux Display Settings:**
1. Settings → Displays
2. Resolution and scale
3. For font rendering:
   - Settings → Appearance (or similar)
   - Font rendering options
   - Hinting: slight or medium usually best
   - Antialiasing: enabled

### Activity 2.5a: Optimize Your Display

_Time: 5 minutes_

**Part 1: Check Current Settings**

Instructions:
1. Navigate to your display settings
2. Note your current scaling percentage
3. Note your current resolution

**Part 2: Assess Readability**

Instructions:
1. Open a document with text in a language you work with
2. Ask yourself:
   - Can I read this comfortably for 30+ minutes?
   - Are small details (diacritics, combining marks) clear?
   - Does text feel too small, too large, or just right?

**Part 3: Adjust if Needed**

If text is too small:
- Try the next scaling level up
- On Windows, may need to sign out/in
- Test with your document again

If text appears blurry:
- Verify you're using native/recommended resolution
- On Windows: Run ClearType tuner
- Adjust until text is sharp

For Windows users specifically, if you haven't run ClearType tuner before, do it now:
1. Search "ClearType" in Start menu
2. Run the "Adjust ClearType text" wizard
3. Choose the text samples that look clearest to you
4. Complete all steps
5. Notice the improvement in text sharpness

**In your learning journal, document:**
1. **Original scaling setting:**
2. **Did you change it? To what?**
3. **Original resolution:**
4. **Did you run ClearType tuner (Windows)?**
5. **How does text readability compare now?**
6. **Is text clear for extended work, especially in non-Latin scripts?**

---

### Common Display Problems and Quick Solutions

**Problem: Text appears blurry or fuzzy**

Solutions:
- Use native/recommended resolution
- Run ClearType tuner (Windows)
- Adjust scaling (try one step different)
- Verify monitor cable is secure

**Problem: Characters show as boxes**

This is a font problem, not display:
- Install the correct font (Section 2.3)
- Select the font in your application

**Problem: Text is too small to read comfortably**

Solutions:
- Increase display scaling
- Increase font size in specific applications
- Sit closer to monitor (or get a larger monitor)

**Problem: Eyes hurt after working**

Solutions:
- Adjust monitor brightness to match room lighting
- Take regular breaks (20-20-20 rule: every 20 min, look 20 feet away for 20 sec)
- Enable night light/night shift (reduces blue light)
- Increase text size if straining to read
- Improve room lighting (reduce glare on screen)

### Best Practices for Display Configuration

**For initial setup:**
- Use native monitor resolution
- Optimize font rendering (ClearType on Windows)
- Set scaling appropriate for monitor size and user eyesight
- Test with actual language work content

**For ongoing use:**
- Regular breaks more important than perfect settings
- Adequate room lighting reduces screen glare
- Clean monitor screen regularly
- Adjust brightness based on time of day
- Consider monitor position and viewing distance

**For teaching users:**
- Show them where settings are
- Demonstrate with their actual work
- Explain why these settings matter
- Encourage them to adjust for their comfort

---

## Module 2 Summary

### You've Learned

In Module 2, you've gained practical configuration skills:

**Installation fundamentals:**
- When reinstallation is necessary (and when it's not)
- How to prepare users so installation goes smoothly
- Your role: preparation and post-installation configuration

**System information:**
- How to quickly gather the specs needed for troubleshooting
- Where to find system information on each platform
- What details matter for compatibility and diagnosis

**Font installation:**
- Why SIL fonts are your first choice for language work
- How to download and install fonts
- Testing fonts and troubleshooting problems

**Input language configuration:**
- How to add keyboard layouts for different languages
- How to switch between input languages
- Teaching users to manage their input languages

**Display optimization:**
- Adjusting scaling for comfortable reading
- Optimizing font rendering (especially ClearType on Windows)
- Common display problems and solutions

### Your Configuration Reference

Your learning journal now contains:

**Documentation:**
- Your system specifications
- Fonts you've installed
- Input languages configured
- Display settings optimized

**Hands-on experience:**
- Installing fonts
- Adding input languages
- Adjusting display settings
- Testing configurations

**Troubleshooting knowledge:**
- Common problems
- Quick solutions
- When to escalate

This becomes your reference as you support users.

### How Module 2 Prepares You for Module 3

Module 3 teaches troubleshooting and maintenance. The skills you learned in Module 2 are what you'll be:
- Restoring when things go wrong
- Adjusting when performance problems arise
- Verifying when diagnosing issues

Understanding proper configuration makes diagnosis much easier - you know what "right" looks like.

### Before Module 3

**Practice these skills:**
- Install another SIL font you haven't tried
- Add a second input language
- Help someone with their display settings
- Create a one-page quick reference for users

**Review your journal:**
- Make sure all activities are documented
- Note any questions for your mentor
- Add any insights from your practice

**Optional deeper exploration:**
- Explore other SIL fonts at software.sil.org/fonts
- Try different keyboard layouts for a language
- Research font rendering in more depth
- Test configurations with different applications

### Assessment Preparation

Module 3 will include assessment scenarios where you apply knowledge from all three modules. To prepare:

**Make sure you can:**
- Quickly document system specifications
- Install a font and verify it works
- Add an input language and teach someone to use it
- Optimize display settings for readability
- Explain when reinstallation is needed

**Areas to clarify:**
- Any steps you're uncertain about
- Platform-specific differences you're unclear on
- How to handle problems you encountered

Discuss these with your mentor before Module 3.

### Practical Application

**Your homework:**
1. **Configure one user's system:**
   - Could be a colleague, friend, family member
   - Apply what you learned (fonts, input, display)
   - Practice teaching, not just doing
   - Document what worked and what was difficult
2. **Create a quick reference guide:**
   - One page for users
   - How to switch input languages
   - Where to adjust display settings
   - Top 3 problems and solutions
3. **Reflect in your journal:**
   - What felt comfortable?
   - What needs more practice?
   - What questions do you still have?

---

**You've completed Module 2!** Take a break before continuing to Module 3: Troubleshooting and Maintenance.
