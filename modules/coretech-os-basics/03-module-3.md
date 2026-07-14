# MODULE 3: TROUBLESHOOTING AND MAINTENANCE

**Duration:** 40-45 minutes  
**Format:** Asynchronous self-paced learning

## Learning Objectives

By the end of this module, you will be able to:

- Use a systematic framework to diagnose OS problems
- Identify and resolve common operating system issues
- Use built-in diagnostic tools effectively
- Perform basic OS maintenance tasks
- Make informed decisions about repair, reinstall, or escalation
- Apply troubleshooting skills to realistic scenarios

## Section 3.1: Module 3 Introduction

_Time: 3 minutes_

### Welcome to Module 3

In Modules 1 and 2, you learned how operating systems work and how to configure them properly for language work. Now you're ready for the most practical skill: troubleshooting when things go wrong.

### Why Troubleshooting Skills Matter

As a language technology consultant, you'll regularly encounter:

**Users with problems:**

- "My computer is really slow"
- "I can't connect to the internet"
- "The system won't start"
- "Everything keeps freezing"

**Your challenge:**

- User might be anywhere in the world
- You're supporting them remotely
- They need to get back to work quickly
- You need to diagnose accurately without seeing the computer

**Your value:**

- Systematic diagnosis (not random guessing)
- Knowing what to check and in what order
- Understanding when problems are fixable vs. when to escalate
- Keeping language workers productive

### What Makes OS Troubleshooting Different

Operating system problems affect everything on the computer. Unlike application problems (affecting one program), OS problems can:

**Block all work:**

- Computer won't start
- Display doesn't work
- Input devices not responding

**Slow everything down:**

- All programs run slowly
- System freezes frequently
- Takes forever to start up

**Create cascading failures:**

- One problem triggers others
- Hard to identify the root cause
- Symptoms appear in multiple places

This is why systematic troubleshooting matters - you need a framework, not just trial and error.

### The Troubleshooting Mindset

Good troubleshooting requires a specific approach:

**Be systematic:**

- Follow a diagnostic framework
- Check common causes first
- Document what you try
- Don't skip steps

**Ask good questions:**

- What changed recently?
- When did this start?
- Does it happen all the time or sometimes?
- Can you reproduce it?

**Think like a detective:**

- Gather evidence before conclusions
- Test hypotheses
- Eliminate possibilities methodically
- Keep notes

**Know your limits:**

- Some problems need specialists
- Recognize when to escalate
- It's okay to say "I don't know, let me research this"

### What You'll Learn in Module 3

This module teaches practical troubleshooting skills:

- **Diagnostic framework** - A systematic approach to diagnosing any OS problem, with tools to gather information and narrow down causes.
- **Common problems and solutions** - The issues you'll encounter most often and how to fix them efficiently.
- **Maintenance practices** - Preventing problems through regular maintenance, and what users can do themselves.
- **Decision-making** - When to repair, when to reinstall, when to escalate to specialists.

### Module Structure

- **Section 3.2 (12 min):** Diagnostic framework and tools
- **Section 3.3 (15 min):** Common OS problems and solutions
- **Section 3.4 (8 min):** Maintenance and prevention
- **Section 3.5 (10 min):** Making decisions and assessment scenarios

### Before You Begin

For this module, you'll need:

- Your learning journal with system information from Module 1
- Access to your computer to explore diagnostic tools
- Willingness to experiment safely with troubleshooting tools

Don't worry - we won't break anything. The tools we'll use are designed to diagnose, not fix, so they're very safe.

## Section 3.2: Diagnostic Framework and Tools

_Time: 12 minutes_

### Introduction

Random troubleshooting wastes time and often makes problems worse. A systematic framework helps you diagnose efficiently and accurately.

### The Diagnostic Framework

This framework works for almost any OS problem:

_The six-step diagnostic framework in this section will feel familiar if you've worked through other courses in this program. Gathering information, isolating the problem, testing one thing at a time — these principles appear whether you're diagnosing hardware failures or font display problems. Operating system troubleshooting applies the same disciplined thinking to a different layer of the system: the software that sits between hardware and applications._

**Step 1: Gather Information**

- What exactly is the problem? (specific symptoms)
- When did it start? (timeline)
- What changed recently? (updates, new software, hardware changes)
- Does it happen consistently or intermittently? (pattern)
- Can you reproduce it? (repeatability)

**Step 2: Isolate the Problem**

- Is it hardware or software?
- Is it system-wide or just one application?
- Does it affect all users or just one?
- Does it happen in safe mode? (isolates third-party software)

**Step 3: Check the Obvious First**

- Is it plugged in and powered on?
- Are cables connected properly?
- Has the system been restarted recently?
- Are there error messages? (read them carefully!)
- Is disk space full?

**Step 4: Research and Hypothesize**

- Search for error messages online
- Check if this is a known issue
- Form a theory about the cause
- Identify what you can test

**Step 5: Test and Verify**

- Test your hypothesis systematically
- Change one thing at a time
- Document what you try
- Verify the problem is actually fixed

**Step 6: Document and Follow Up**

- Record the problem and solution
- Monitor to ensure problem doesn't return
- Update your troubleshooting notes

### Key Principle: One Change at a Time

**Critical rule:** When testing solutions, change only one thing at a time.

**Why this matters:** If you make multiple changes and the problem is fixed, you won't know which change actually worked. Worse, if the problem persists, you won't know if any of your changes helped or if they made things worse.

**Example - Wrong approach:**

- Computer is slow
- Update all drivers, install antivirus, uninstall 5 programs, change power settings, defragment disk
- Computer still slow
- Which change helped? Which hurt? You don't know.

**Example - Right approach:**

- Computer is slow
- Check Task Manager - see high CPU usage
- Identify specific program causing high CPU
- Close that program
- Computer faster - problem identified
- Research why that program was using so much CPU

### Built-in Diagnostic Tools

Every operating system includes tools for diagnosing problems. Learning to use them is essential.

**Windows Diagnostic Tools:**

**Task Manager** (`Ctrl + Shift + Esc`):

- Shows running processes and resource usage
- See what's using CPU, memory, disk
- End unresponsive programs
- Check startup programs

**Resource Monitor** (from Task Manager → Performance tab):

- More detailed than Task Manager
- Real-time monitoring of CPU, memory, disk, network
- See which processes use which resources

**Event Viewer:**

- System logs of errors and warnings
- Can identify recurring problems
- Technical but very informative
- Search Start menu for "Event Viewer"

**Windows Memory Diagnostic:**

- Tests RAM for problems
- Run from Start menu search
- Requires restart
- Use when suspecting memory issues

**System File Checker** (command line):

- Checks and repairs system files
- Run `sfc /scannow` in administrator command prompt
- Takes time but can fix corruption

**macOS Diagnostic Tools:**

**Activity Monitor** (Applications → Utilities):

- Like Windows Task Manager
- Shows CPU, memory, energy, disk, network usage
- Force quit unresponsive apps

**Console** (Applications → Utilities):

- System logs and messages
- See errors and warnings
- Technical but helpful for diagnosis

**Disk Utility:**

- Check and repair disk problems
- First Aid function
- Run from Applications → Utilities

**Apple Diagnostics** (hardware):

- Hold D key during startup
- Tests hardware components
- Gives error codes if problems found

**Linux Diagnostic Tools:**

**System Monitor** (varies by desktop):

- Ubuntu: Search for "System Monitor"
- Shows processes, resources, file systems
- Similar to Windows Task Manager

**Command line tools:**

- `top` — real-time process monitor
- `htop` — better version of top (may need install)
- `df -h` — disk space usage
- `free -h` — memory usage
- `dmesg` — system messages and errors
- `journalctl` — system logs (systemd)

**Disk utilities:**

- GNOME Disks (graphical)
- `fsck` (command line, checks file systems)

### Activity 3.2a: Explore Diagnostic Tools

_Time: 7 minutes_

Now you'll explore the diagnostic tools on your system.

**Part 1: Resource Monitor**

**Instructions:**

1. Open your system's resource monitor:
   1. Windows: Task Manager (`Ctrl + Shift + Esc`)
   2. macOS: Activity Monitor
   3. Linux: System Monitor
2. Look at the current state of your system:
   1. What percentage of CPU is being used?
   2. What percentage of memory (RAM) is being used?
   3. How much disk space is available?
3. Sort by CPU usage (click the CPU column header)
   1. What process is using the most CPU right now?
4. Sort by memory usage
   1. What process is using the most memory?

**In your learning journal, document:**

1. **Current resource usage:**
   1. CPU: ____%
   2. Memory: ____%
   3. Free disk space: _____
2. **Top CPU user:** (process name)
3. **Top memory user:** (process name)
4. **Observations:**
   1. Does your system seem to have resources available, or is it heavily loaded?
   2. Are there processes you don't recognize?
   3. Would this information help diagnose a "slow computer" problem?

**Part 2: Explore System Logs (optional)**

If you want to go deeper:

**Windows users:**

- Search "Event Viewer"
- Open it
- Look at Windows Logs → System
- Notice the list of events (Information, Warning, Error)
- Don't worry about understanding everything - just observe

**macOS users:**

- Open Console (Applications → Utilities → Console)
- Look at system messages
- Notice types: Error, Fault, Default

**Linux users:**

- Open terminal
- Run `journalctl -b` (shows log for current boot)
- Or run `dmesg | tail` (shows recent system messages)

**In your learning journal:**

- Were you able to find system logs?
- Do you see any error messages?
- Would this be useful for troubleshooting? When?

**Note:** Understanding logs takes practice and isn't always necessary. Knowing they exist and where to find them is the important first step.

### Information Gathering Questions

When users report problems, asking the right questions is critical. Here's your checklist:

**About the problem:**

- What exactly is happening? (be specific - "slow" could mean many things)
- What were you doing when it started?
- Is there an error message? (ask user to read it exactly or take a photo)

**About timing:**

- When did this start? (today? last week? after an update?)
- Does it happen all the time or only sometimes?
- If sometimes, what's different when it happens vs. doesn't happen?

**About changes:**

- What changed recently? (this is often the key question!)
  - Software installed or updated?
  - Operating system update?
  - New hardware connected?
  - Settings changed?
  - Power outage or unexpected shutdown?

**About scope:**

- Does this affect everything or just one program?
- Does it happen for all users or just one?
- Does it happen in safe mode? (advanced question)

**About attempts:**

- Have you tried anything already?
- What happened when you tried that?
- Has this problem happened before?

### Common Diagnostic Patterns

Certain patterns of symptoms point to common causes:

**Gradual slowdown over time:**

- Likely: disk space filling up, too many startup programs, fragmented disk (on HDD), malware
- Check: disk space, startup programs list, run antivirus scan

**Sudden slowdown after update:**

- Likely: update caused problem, driver incompatibility, new background service
- Check: recent updates, can you roll back? Device Manager for driver issues

**Won't start at all:**

- Likely: hardware failure, corrupted system files, failed update
- Check: does hardware POST (beep/logo appear)? Safe mode work? Hardware diagnostics

**Frequent freezing:**

- Likely: bad RAM, overheating, driver problem, disk failure
- Check: memory diagnostic, temperature, Event Viewer for errors

**Specific program crashes:**

- Likely: program problem, not OS (unless affecting many programs)
- Check: reinstall program, check for updates, verify system requirements

### Key Takeaways: Section 3.2

- Systematic troubleshooting beats random guessing
- Gather information before trying solutions
- Ask good questions about what, when, what changed
- Built-in diagnostic tools help identify problems
- Change one thing at a time when testing solutions
- Document what you learn for future reference

## Section 3.3: Common OS Problems and Solutions

_Time: 15 minutes_

### Introduction

While every problem is unique, certain issues appear repeatedly. Learning to recognize and fix these common problems makes you efficient and effective.

### Startup and Boot Problems

**Problem: Computer won't start at all**

**Symptoms:**

- No lights, no sounds, completely dead
- Or: lights on but no display

**Common causes and solutions:**

If completely dead:

- Check power connections (plug, battery)
- Try different power outlet
- Check if power adapter is working (test with meter or try another if available)
- **This is likely hardware** - may need specialist

If lights/sounds but no display:

- Check monitor connections
- Check monitor power
- Try different monitor if available
- Listen for beep codes (can indicate hardware failure)
- **May be hardware (display or graphics)** - escalate if not simple connection issue

**Problem: Computer starts but OS won't load**

**Symptoms:**

- Manufacturer logo appears but then error message
- Blue screen (Windows)
- Kernel panic (Mac/Linux)
- Spinning circle forever

**Common causes and solutions:**

Try Safe Mode:

- **Windows:** hold Shift while clicking Restart, choose Troubleshoot → Advanced → Startup Settings → Safe Mode
- **macOS:** hold Shift during startup
- **Linux:** select recovery mode from GRUB menu

If Safe Mode works:

- Recent update or software may be the problem
- Uninstall recently installed software
- Roll back recent updates if possible
- System file corruption - may need repair

If Safe Mode doesn't work:

- More serious problem
- Potential disk failure
- Corrupted system files
- May need reinstallation or specialist help

### Performance Problems

**Problem: Computer is very slow**

**Symptoms:**

- Takes long time to start
- Programs slow to open
- Everything feels sluggish

**Diagnostic steps:**

1. **Check disk space:**
   1. Less than 10% free space causes slowness
   2. Solution: delete unnecessary files, empty recycle bin, remove old downloads
2. **Check Task Manager/Activity Monitor:**
   1. Is CPU at 100%? What process?
   2. Is memory (RAM) at 100%?
   3. Is disk at 100%? (Windows Task Manager)
3. **Too many startup programs:**
   1. Windows: Task Manager → Startup tab
   2. macOS: System Settings → General → Login Items
   3. Disable unnecessary startup programs
4. **Background updates or scans:**
   1. Windows Update running?
   2. Antivirus scanning?
   3. Cloud sync running (OneDrive, Dropbox)?
   4. Wait for completion or schedule for different time
5. **Malware possibility:**
   1. Run full antivirus scan
   2. Check for unfamiliar programs in installed programs list
   3. Look for unknown processes using lots of resources

**Solutions by cause:**

**Low disk space:**

- Delete files, empty trash, remove old downloads
- Uninstall unused programs
- Move files to external drive

**Too many startup programs:**

- Disable unnecessary ones (keep antivirus, important tools only)
- Restart to apply changes

**Insufficient RAM for workload:**

- Close unnecessary programs
- Consider RAM upgrade if possible
- Work with fewer programs open simultaneously

**Disk drive failing (HDD):**

- BACKUP IMMEDIATELY
- Check disk health with diagnostic tools
- May need drive replacement

**Problem: System freezes frequently**

**Symptoms:**

- Mouse cursor stops moving
- Can't click anything
- Must force restart

**Common causes:**

**Bad RAM:**

- Run memory diagnostic (Windows Memory Diagnostic, memtest86)
- If errors found, RAM needs replacement

**Overheating:**

- Feel if computer is very hot
- Check vents aren't blocked
- May need cleaning or fan repair
- Laptops: use on hard surface, not soft surfaces that block vents

**Driver problems:**

- Check Event Viewer for driver errors
- Update drivers, especially graphics
- Roll back recent driver updates

**Disk problems:**

- Run disk check utility
- Check SMART status if possible
- May indicate failing drive

### Network and Connectivity Problems

**Problem: Can't connect to internet**

**Diagnostic steps:**

1. **Check physical connections:**
   1. Ethernet cable plugged in?
   2. WiFi turned on?
   3. Router powered on?
2. **Is it just this computer or everything?**
   1. Check other devices
   2. If all devices affected: router problem
   3. If just this computer: computer problem
3. **Try basic fixes:**
   1. Restart computer
   2. Restart router (unplug 30 seconds, plug back in)
   3. Toggle WiFi off and on
4. **Check settings:**
   1. Airplane mode off?
   2. WiFi connected to correct network?
   3. Correct password entered?
5. **Advanced checks:**
   1. Can you see WiFi networks? (if not: WiFi hardware problem)
   2. Connected but no internet? (network settings or ISP problem)
   3. Try wired connection if WiFi doesn't work (isolates WiFi from general network)

**Solutions:**

**WiFi problems:**

- Forget network and reconnect (re-enter password)
- Update WiFi drivers (Windows)
- Reset network settings (last resort)

**DNS problems:**

- Try using Google DNS (8.8.8.8 and 8.8.4.4)
- Or Cloudflare DNS (1.1.1.1)
- Change in network adapter settings

**ISP/router problems:**

- Contact internet provider
- Check if service outage in area
- May need router restart or reset

### Display and Graphics Problems

**Problem: Display looks wrong**

**Symptoms:**

- Resolution too low (everything looks big and blocky)
- Colors look strange
- Text blurry
- Screen flickers

**Solutions:**

**Wrong resolution:**

- Set to native/recommended resolution
- Windows: Settings → System → Display
- macOS: System Settings → Displays
- Should be set to maximum available (usually has "(Recommended)" next to it)

**Blurry text:**

- Check scaling settings
- Run ClearType tuner (Windows)
- Verify using native resolution

**Driver problems:**

- Update graphics drivers
- Windows: Device Manager → Display adapters
- Can cause display issues, flickering, poor performance

**Multiple monitor problems:**

- Check physical connections
- Detect displays in settings
- Set correct primary monitor
- Arrange monitors to match physical layout

### Update and Software Problems

**Problem: Update failed or caused problems**

**Symptoms:**

- Update won't install
- System problems started after update
- Constant restart loop

**Solutions:**

**Update won't install:**

- Check disk space (needs space for update files)
- Run Windows Update troubleshooter (Windows)
- Check if antivirus blocking
- Download update manually if automatic fails

**Problems after update:**

- Roll back update if possible (Windows: Settings → Update & Security → View Update History → Uninstall updates)
- Restore from system restore point (if enabled)
- Check if known issues for that update (search online)
- Wait for fix update from Microsoft/Apple

**Stuck in restart loop:**

- Try Safe Mode
- Roll back problematic update
- May need system restore or reinstall if can't boot normally

### Activity 3.3: Diagnose Scenarios

_Time: 7 minutes_

Practice applying your troubleshooting framework to realistic scenarios.

**Scenario 1: Slow Performance**

Maria contacts you: "My computer has become really slow over the last week. It takes forever to start up and programs are slow to open. I haven't installed anything new."

**In your learning journal, write:**

1. **What questions would you ask Maria to gather more information?**
2. **What are 3 possible causes for this problem?**
3. **What would you check first? (in order)**
4. **If you found disk space at 98% full, what would you tell Maria to do?**

**Scenario 2: Can't Type in Language**

John reports: "I was working on a translation yesterday and everything was fine. Today I tried to type in Amharic but I just get English letters no matter what I do. I can still type in English fine."

**In your learning journal, write:**

1. **What do you think is the most likely cause?**
2. **What would you ask John to check first?**
3. **If the Amharic input language is still listed in his settings, what would you tell him to try?**
4. **What if the Amharic input language is missing from the list?**

**Scenario 3: Won't Start**

Sarah emails: "My laptop won't start this morning. When I press the power button, the lights come on for a second and then it goes off again. It was working fine yesterday."

**In your learning journal, write:**

1. **Is this likely a hardware or software problem?**
2. **What would you tell Sarah to check first?**
3. **What might have caused this sudden problem?**
4. **Should you try to troubleshoot this remotely or escalate to a hardware specialist?**

### When to Suspect Hardware vs. Software

**Hardware problems usually:**

- Appear suddenly (were fine, now broken)
- Happen consistently (not intermittent)
- Aren't fixed by restart or reinstall
- Show physical symptoms (strange sounds, heat, won't power on)
- May show in hardware diagnostics

**Software problems usually:**

- Develop gradually or after changes (update, new software)
- May be intermittent (sometimes works, sometimes doesn't)
- Often fixed by restart
- Related to specific programs or functions
- Show in system logs or Event Viewer

**Hybrid problems:**

- Overheating (hardware stress causes software crashes)
- Failing hard drive (hardware failing, causes data corruption)
- Bad RAM (hardware fault, causes random crashes)
- Driver issues (software talking to hardware incorrectly)

### Key Takeaways: Section 3.3

- Common problems have recognizable patterns
- Check simple things first (disk space, connections, restarts)
- Systematic diagnosis beats random fixing
- Hardware vs. software distinction guides troubleshooting
- Document solutions that work for future reference
- Some problems need specialist help - recognize when to escalate

## Section 3.4: Maintenance and Prevention

_Time: 8 minutes_

### Introduction

Preventing problems is better than fixing them. Regular maintenance keeps systems running smoothly and prevents many common issues.

### Why Maintenance Matters

**For language workers specifically:**

- Downtime during critical project phases is costly
- Lost work due to crashes or corruption
- Slow systems reduce productivity
- Problems compound over time if ignored

**Your role:**

- Teach users basic maintenance they can do themselves
- Schedule and perform deeper maintenance
- Catch small problems before they become big ones

### Regular Maintenance Tasks

**Daily (user responsibility):**

- Shut down properly (don't just close laptop lid if doing updates)
- Notice unusual behavior (new sounds, slowness, errors)
- Save work frequently
- Report problems promptly

**Weekly:**

- Clear browser cache and downloads folder
- Empty recycle bin/trash
- Close unused programs
- Check for critical updates

**Monthly:**

- Check available disk space
- Review installed programs (remove unused)
- Check backup is working
- Update important software
- Restart computer (if not done recently)

**Quarterly:**

- Install OS updates
- Review startup programs
- Check for driver updates (especially graphics)
- Scan for malware
- Review and clean up files

**Annually:**

- Full system backup
- Review system performance
- Consider if upgrade needed
- Clean computer physically (dust in vents)
- Verify recovery methods work

### Disk Space Management

**Why disk space matters:** Systems slow dramatically when disk space is low (less than 10-15% free). The OS needs space for temporary files, updates, and virtual memory.

**Common space hogs:**

- Downloads folder (files accumulate)
- Temporary files (system and browser)
- Old installers (no longer needed after installation)
- Duplicate files
- Old email attachments
- Cache files
- Recycle bin/trash not emptied

**How to free space:**

1. **Empty recycle bin/trash**
2. **Clear downloads folder** (move keepers to proper location, delete rest)
3. **Use Disk Cleanup** (Windows) or similar tools
4. **Remove unused programs**
5. **Clear browser cache** (in browser settings)
6. **Delete old files you no longer need**
7. **Move large files to external storage** (videos, old projects)

**Setting expectations with users:** Tell them: "Keep at least 20% of your disk free for best performance. Check monthly."

### Update Management

**Types of updates:**

**Security updates:**

- Fix vulnerabilities
- Should be installed promptly
- Usually safe

**Feature updates:**

- Add new features
- Can wait if system working well
- Test on non-critical system first if possible

**Driver updates:**

- Update hardware support
- Only update if having problems or new feature needed
- "If it ain't broke, don't fix it" applies here

**Application updates:**

- Keep language software updated
- Check release notes for important fixes
- Test before deploying to all users

**Update best practices:**

**For consultants:**

- Know what updates are available
- Plan update timing (not during critical project phases)
- Backup before major updates
- Test updates on one system before rolling out widely

**For users:**

- Enable automatic security updates
- Approve feature updates when convenient
- Don't ignore update notifications indefinitely
- Report problems after updates promptly

### Backup Importance

**Critical reality:** Hard drives fail. Computers get lost or stolen. Accidents happen.

**What to backup:**

- All user-created files (documents, translations, data)
- Email (if stored locally)
- Browser bookmarks
- Application data and settings
- License keys and installation files for critical software

**Backup strategies - the 3-2-1 rule:**

- **3** copies of important data
- On **2** different types of media
- With **1** copy off-site

**Practical for language workers:**

- Original data on computer
- Backup on external hard drive
- Cloud backup (OneDrive, Google Drive, Dropbox) or second external drive kept elsewhere

**Backup frequency:**

- Daily for active project files (automatic cloud sync works well)
- Weekly for full system
- Before major changes (OS update, reinstall, etc.)

**Testing backups:**

- Periodically try restoring a file
- Verify backup is actually working
- Untested backup = no backup

### Activity 3.4: Create a Maintenance Checklist

_Time: 4 minutes_

Create a practical maintenance checklist for users you support.

**Instructions:** In your learning journal, create a simple checklist that users can follow.

**Your checklist should include:**

**Monthly Maintenance (15 minutes):**

- [ ] Check disk space (should have 20%+ free)
- [ ] Clear downloads folder
- [ ] Empty recycle bin/trash
- [ ] Check for OS updates
- [ ] Restart computer if not done recently

**Quarterly Maintenance (30 minutes):**

- [ ] Review and remove unused programs
- [ ] Check backup is working
- [ ] Scan for malware
- [ ] Update important software
- [ ] Check startup programs (disable unnecessary ones)

**Warning Signs - Report Immediately:**

- [ ] Strange noises from computer
- [ ] Computer unusually hot
- [ ] Frequent freezing or crashes
- [ ] Disk space warning
- [ ] Persistent error messages

**Add to this checklist:**

- Specific items for your context
- Links to tools they need
- Your contact information for support

**Why create this:**

- Gives users clear actionable steps
- Prevents many common problems
- Creates shared responsibility for system health
- Reduces emergency support calls

### Preventing Common Problems

**To prevent slowness:**

- Keep disk space available
- Limit startup programs
- Restart regularly
- Don't run too many programs simultaneously

**To prevent crashes:**

- Install updates
- Use good power supply (surge protector)
- Keep system cool (good ventilation)
- Don't force shutdown unless necessary

**To prevent data loss:**

- Backup regularly
- Use save/autosave features
- Don't work directly from external drives (copy to computer first)
- Verify backups work

**To prevent malware:**

- Keep antivirus updated and running
- Don't click suspicious links
- Download only from trusted sources
- Be skeptical of too-good-to-be-true offers

**To prevent configuration problems:**

- Document settings before changing them
- Change one thing at a time
- Know how to undo changes
- Don't change things you don't understand

### Teaching Users About Maintenance

**What users need to understand:**

**Maintenance is normal:**

- Like changing oil in a car
- Prevents bigger problems
- Takes time but saves time later

**They can do basic maintenance themselves:**

- Not all computer problems need a specialist
- Following checklist keeps system healthy
- Builds confidence and independence

**When to ask for help:**

- Warning signs (checklist above)
- Problems beyond their skill level
- Before making major changes
- When unsure what to do

**Create learning opportunities:**

- Walk through maintenance with user once
- Have them do it while you watch second time
- Check in monthly: "Did you do maintenance?"
- Praise them for preventing problems

### Key Takeaways: Section 3.4

- Regular maintenance prevents most common problems
- Users can handle basic maintenance themselves
- Disk space and updates are critical
- Backup is essential - test it regularly
- Teach maintenance, don't just do it for users
- Prevention is easier than repair

## Section 3.5: Making Decisions and Assessment

_Time: 10 minutes_

### Introduction

Troubleshooting isn't just technical skills - it's also making good decisions about what to do when you can't fix something yourself, when reinstallation is worth it, and when to bring in specialists.

### The Decision Framework

When facing a problem, you have several options:

**1. Fix it yourself (remotely or hands-on)**

- Appropriate when: problem is within your skill level, you have necessary access, risk is low, user can work with your guidance

**2. Guide the user to fix it**

- Appropriate when: user is capable, problem is straightforward, good learning opportunity, you can provide clear instructions

**3. Escalate to local IT support**

- Appropriate when: problem needs hands-on access, hardware diagnosis needed, beyond your expertise, local support available and capable

**4. Escalate to specialist**

- Appropriate when: complex hardware problem, data recovery needed, problem is critical and you're uncertain, specialist knowledge required

**5. Work around the problem temporarily**

- Appropriate when: immediate fix not possible, user needs to keep working, permanent solution requires time/resources not immediately available

**6. Accept and adapt**

- Appropriate when: "problem" is actually a limitation, fixing would cost more than benefit, workaround is acceptable long-term

### Repair vs. Reinstall Decision

One of the most common decisions: spend time troubleshooting or just reinstall?

**Consider reinstalling when:**

_Time factor:_

- Troubleshooting has taken 2+ hours with no resolution
- Reinstall would be faster than continued diagnosis
- User needs system working urgently

_Severity factor:_

- Multiple serious problems (not just one issue)
- System corruption suspected
- Malware that can't be fully cleaned
- System has deteriorated over years

_Cost-benefit:_

- User's time very valuable
- You have good backup
- System needs fresh start anyway
- Troubleshooting risk is high

**Consider troubleshooting longer when:**

_Time factor:_

- Problem clearly diagnosed, just need to apply fix
- Reinstall would take longer (special software, complex configuration)
- Not urgent - can take time to investigate

_Uniqueness factor:_

- Custom configuration difficult to recreate
- Specialized software hard to reinstall
- Licenses or activation complicated

_Learning factor:_

- You want to learn from this problem
- Diagnosis will help prevent future issues
- Curious what caused it

_Risk factor:_

- Backup uncertain or incomplete
- Reinstall itself is risky (driver availability concerns)
- Might lose important data

### Hardware vs. Software: When to Escalate

**Definitely escalate hardware problems:**

- Physical damage (broken screen, keys, ports)
- Liquid damage
- Strange noises, burning smell
- Won't power on at all
- System overheating despite cleaning vents

**Maybe escalate (depends on your skills/access):**

- Hard drive replacement
- RAM upgrade or replacement
- Battery replacement
- Cleaning dust from inside
- Peripheral problems (if clearly hardware)

**Usually handle yourself (software):**

- OS configuration and settings
- Software installation and updates
- Performance optimization
- User account issues
- Most network connectivity issues

### Cost-Benefit Analysis

Sometimes the "best" technical solution isn't the right practical solution.

**Factors to consider:**

**User downtime cost:**

- Is this their only computer?
- Is project deadline approaching?
- How critical is their work?
- Can they use another computer temporarily?

**Repair cost vs. replacement cost:**

- Expensive repair on old computer?
- Might be better to replace
- "Throw good money after bad"
- Consider age and expected remaining life

**Data value:**

- How valuable is the data?
- Is backup available?
- Is data recovery needed?
- Worth spending more to recover data?

**Learning vs. efficiency:**

- Is this a learning opportunity?
- Or is efficiency more important?
- Balance your professional development with user needs

### Communicating Uncertainty

You won't always know the answer. That's okay. How you handle uncertainty matters.

**Good approaches:**

**Honest about limits:**

- "I'm not certain what's causing this, but here's what I think..."
- "This is outside my experience, but let me research it"
- "I could keep trying, but we might want to bring in a specialist"

**Collaborative problem-solving:**

- "Let's try this and see what happens"
- "I want to test something - are you okay if we experiment?"
- "I'm learning about this too - let's figure it out together"

**Manage expectations:**

- "This might take a few tries"
- "I'll need some time to research this"
- "There's a chance we might not be able to fix this without reinstalling"

**Bad approaches:**

**Pretending to know:**

- "Oh yeah, I know exactly what this is" (when you don't)
- Making up explanations
- Trying random things without explaining uncertainty

**Blaming the user:**

- "What did you do to break this?"
- "You must have clicked something you shouldn't have"
- Implying they caused the problem (even if they did)

**Being defensive:**

- "Well, I've never seen this before"
- "This isn't supposed to happen"
- Getting frustrated or giving up

### Assessment Scenarios

Now apply everything you've learned from all three modules.

#### Scenario: Complete Diagnostic Challenge

**Context:** Pastor David in rural Uganda contacts you. He's translating using Paratext on a Windows 10 laptop. The computer has become very slow over the past week. He reports that when he opens Paratext, it takes several minutes to load, and typing has a noticeable delay.

**Your system information request reveals:**

- Windows 10, 64-bit
- Intel Core i3, 4 GB RAM
- 500 GB hard drive, 30 GB free (6% free)
- Computer is 5 years old
- Working from a location with intermittent electricity

**Additional information from questions:**

- Slowness started about a week ago
- Nothing new installed recently
- Windows Update ran a few days ago
- He works with a 2 GB Paratext project
- Has external hard drive but hasn't backed up in 2 months

**In your learning journal, write a complete response:**

1. **Initial diagnosis:**
   1. What do you think is the primary cause of the slowness?
   2. What other factors might be contributing?
   3. What additional questions would you ask?
2. **Immediate action plan:**
   1. What would you tell Pastor David to do first?
   2. What order would you try solutions?
   3. What should he do while troubleshooting?
3. **Short-term solutions:**
   1. What can be fixed quickly to get him working again?
   2. What is good enough for now?
4. **Long-term recommendations:**
   1. What should be done to prevent this recurring?
   2. Should any hardware be upgraded?
   3. What maintenance plan would you suggest?
5. **Risk management:**
   1. What are the risks in this situation?
   2. What should be done to protect his work?
   3. What's your backup plan if your solutions don't work?

#### Scenario: Decision-Making Challenge

**Context:** Sarah runs a literacy program and has a laptop that's having multiple problems:

- Frequent crashes (2-3 times per day)
- Slow startup (10+ minutes)
- Can't install Windows updates (error message)
- Some fonts not displaying correctly

She has:

- Good backup from yesterday
- Windows 10 product key
- All installation files for her language software
- No IT support locally
- Important workshop in 3 days where she needs the laptop

**In your learning journal, analyze:**

1. **Options analysis:**
   1. List at least 3 different approaches you could take
   2. For each approach, list pros and cons
   3. Estimate time required for each approach
2. **Your recommendation:**
   1. What would you recommend and why?
   2. What factors were most important in your decision?
   3. What would you tell Sarah about timeline and expectations?
3. **Risk mitigation:**
   1. What could go wrong with your chosen approach?
   2. What's your backup plan?
   3. How would you ensure she can do her workshop regardless?
4. **Reflection:**
   1. Is this a repair, reinstall, or workaround situation?
   2. Would your answer be different if the workshop was in 1 week? 1 day?
   3. What if she had no backup?

#### Scenario: Escalation Decision

**Context:** Thomas reports his laptop "making a clicking noise" and sometimes the screen "goes black for a few seconds then comes back." He's in the middle of translation checking and can't afford downtime. The computer is 2 years old.

**In your learning journal, write:**

1. **Problem assessment:**
   1. What do these symptoms suggest?
   2. Is this software or hardware?
   3. How serious is this?
2. **Escalation decision:**
   1. Should this be escalated? To whom?
   2. What's urgent about this situation?
   3. What should Thomas do immediately?
3. **Communication:**
   1. What would you tell Thomas about his situation?
   2. How would you explain the risk?
   3. What temporary solution might help him finish his current work?
4. **Preventive lesson:**
   1. What should Thomas have done differently?
   2. What should he do after this is resolved?
   3. How could this have been prevented?

## Module 3 Summary

### You've Learned

In Module 3, you've developed practical troubleshooting and maintenance skills:

**Diagnostic framework:**

- Systematic approach to any OS problem
- Information gathering questions
- Built-in diagnostic tools
- One-change-at-a-time testing
- Hardware vs. software distinction

**Common problems and solutions:**

- Startup and boot problems
- Performance issues (slow, freezing)
- Network connectivity problems
- Display and graphics issues
- Update-related problems
- Pattern recognition for quick diagnosis

**Maintenance and prevention:**

- Regular maintenance tasks
- Disk space management
- Update management
- Backup strategies
- Teaching users self-maintenance
- Preventing common problems

**Decision-making:**

- Repair vs. reinstall decision framework
- When to escalate
- Cost-benefit analysis
- Communicating uncertainty
- Managing user expectations

### Your Troubleshooting Portfolio

Your learning journal now contains:

**Reference materials:**

- System specifications (Module 1)
- Configuration notes (Module 2)
- Troubleshooting framework
- Common problems and solutions
- Maintenance checklist

**Practical experience:**

- Using diagnostic tools
- Analyzing scenarios
- Making decisions
- Creating user documentation

**Assessment responses:**

- Three complete scenario analyses
- Decision-making practice
- Your reasoning documented

### Applying Your Skills

You now have the complete skill set for OS fundamentals:

- **Module 1:** Understanding operating systems and identifying what you're working with
- **Module 2:** Configuring systems properly for language work
- **Module 3:** Troubleshooting problems and maintaining systems

**Together, these skills enable you to:**

- Support language workers effectively
- Diagnose and fix common problems
- Make informed decisions about complex situations
- Teach users to maintain their own systems
- Know when to escalate to specialists
- Keep language work productive

### Next Steps in Your Development

**"Has Knowledge" → "With Assistance" → "Independent"**

This course built your "Has Knowledge" level competency. Moving forward:

**For "With Assistance" development:**

- Work with experienced consultants
- Shadow IT professionals during installations
- Handle problems with mentor backup
- Build experience with diverse scenarios
- Learn from mistakes in low-risk situations

**For "Independent" development:**

- Handle problems on your own
- Mentor others
- Develop specialised expertise in your region
- Create resources for your community
- Contribute to knowledge sharing

**Continue learning:**

- Technology changes constantly
- New OS versions bring new challenges
- Stay current through practice and professional development
- Learn from every problem you encounter

### Mentor Review

Before completing this course, schedule time with your mentor to review:

**Your learning journal:**

- System documentation
- Configuration notes
- Scenario responses
- Maintenance checklist

**Your understanding:**

- Discuss scenario decisions
- Explain your reasoning
- Ask questions about unclear areas
- Get feedback on your approach

**Your next steps:**

- Identify areas needing more practice
- Plan supervised hands-on experience
- Set goals for skill development
- Arrange ongoing mentorship

### Final Reflection

In your learning journal, write a final reflection:

1. **What was most valuable in this course?**
2. **What feels comfortable in your skill set?**
3. **What needs more practice?**
4. **How will you apply this in your work?**
5. **What questions remain?**

### Course Completion

**You've completed the Operating Systems for Language Technology Consultants course!**

You've learned to:

- Understand how operating systems work
- Identify and document system information
- Configure systems for language work
- Install fonts and input languages
- Optimize display settings
- Troubleshoot common problems
- Perform regular maintenance
- Make informed decisions about complex issues
- Know when to escalate to specialists

**These foundational skills support everything else you'll learn in the Language Technology Consultant training program.**

Continue developing through practice, mentorship, and real-world experience.

Well done!
