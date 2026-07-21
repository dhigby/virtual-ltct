# Module 3: Configuring System Settings

**Estimated time:** 60 minutes

**Purpose:** A keyboard that requires constant manual switching tends to get abandoned. This module makes keyboard switching seamless instead of a manual chore, so the tool actually gets used day to day.

## Learning objectives

By the end of this module, you will be able to:
- Associate a Keyman keyboard with a Windows system language
- Configure Windows so the correct keyboard loads automatically when you switch languages
- Switch between keyboards reliably, using more than one method

## Connect

Here's a scenario that comes up constantly in the field: someone has Keyman installed and a keyboard that works perfectly — but every time they open a new application, they have to manually switch back to their language keyboard. The system keeps defaulting to English. It works, but it's annoying enough that some people stop using it.

**Quick check:** When you switch the display language in Windows settings, does your keyboard automatically change to match? If you're not sure, try it now and see what happens.

This module is about making Windows and Keyman work together properly — so keyboard switching becomes seamless rather than manual.

## Content

### How Windows and Keyman work together

Windows links keyboards to languages. When you switch from English to French, Windows switches to whatever keyboard is assigned to French. We can make Keyman part of that system — so switching to a language in Windows automatically activates your Keyman keyboard.

Once this is set up, you won't need to manually click the Keyman icon every time. The system handles it.

### Step 1: Link your keyboard to a Windows language

1. Click the Keyman icon → open Keyman Configuration
2. Find your keyboard in the list
3. Look for an option to associate it with a Windows language
4. Select the matching language (for example, link an Amharic keyboard to Amharic)
5. Click OK

**TIP** If the language you need isn't in the Windows list, you'll need to add it first. Go to **Windows Settings → Time & Language → Language & Region → Add a language**. After that, come back and finish the Keyman step.

### Step 2: Make Keyman the default keyboard for that language

Adding a language to Windows doesn't automatically use your Keyman keyboard. Windows might assign a different keyboard instead. Here's how to fix that:

1. Go to **Windows Settings → Time & Language → Language & Region**
2. Click on your language → Options
3. Under Keyboards, you should see your Keyman keyboard. If something else is listed first, remove it, or move Keyman to the top.

After this, Windows will use your Keyman keyboard automatically whenever that language is active.

### Step 3: Switch between keyboards

There are a few ways to switch between keyboards when you're working.

- **Method 1 — Keyman icon:** Click the Keyman icon in the system tray and select the keyboard you want from the list. This always works and is the most reliable method.
- **Method 2 — Windows keyboard shortcut:** Press `Win + Space` to cycle through your installed system languages (including Keyman keyboards).
- **Method 3 — Keyman's Open Language Switcher hotkey:** Keyman itself has a configurable "Open Language Switcher" hotkey, bound by default to `Left Alt + Shift`. It does cycle through your installed keyboards on Windows 11.

**WARNING** `Left Alt + Shift` has a real quirk worth knowing about: it always applies on **Screen 1** of a multi-monitor setup, even if the window you're actually working in is on Screen 2. If you use two monitors and switching seems to do nothing, check whether it actually switched on the other screen before assuming it's broken.

### Setting custom hotkeys

Keyman Configuration has its own **Hotkeys** panel (Keyman Configuration → Hotkeys), separate from Windows' own shortcuts. It lists several configurable actions, most set to "(no hotkey)" by default:

- Open Language Switcher — default `Left Alt + Shift`
- Show On Screen Keyboard Pane — default `Shift + Alt + K`
- Show Font Helper Pane
- Show Character Map
- Open Text Editor — default `Ctrl + K`
- Open Configuration

You can also set a hotkey for an individual keyboard layout. Double-click a keyboard's hotkey field to open the **Change Hotkey** dialog, which offers **No Hotkey**, **Left Alt + Shift**, **Ctrl + Shift**, or **Custom** (hold Ctrl/Shift/Alt and type your own combination).

**TIP** If you switch keyboards frequently and `Win + Space` or the multi-monitor quirk above is getting in your way, setting a per-keyboard custom hotkey here can save real time — just make sure it doesn't conflict with shortcuts you use in other applications.

## Challenge

Put this into practice with your installed keyboard.

**✏️ Activity 1:** Assign your installed keyboard to a Windows language — use Haitian Creole if you want to test with something less likely to conflict with your default setup, or use the language you actually need.

**✏️ Activity 2:** Switch between your default keyboard and your language keyboard using both the Keyman icon and `Win + Space`. Confirm that switching works in at least two different applications (e.g., Word and Notepad).

Once you've done both, work through the checklist below and tick each item:

- [ ] Keyboard installed
- [ ] Associated with a Windows system language
- [ ] Keyboard switching works using the Keyman icon
- [ ] Keyboard switching works using Win + Space
- [ ] Switching works in at least two different applications

## Change

You've now moved from "Keyman is installed" to "Keyman is properly integrated into my system." That's a meaningful step — a keyboard that requires constant manual switching tends to get abandoned.

**✏️ Reflection:** Think about a colleague or community member you might help set this up for. What's one thing you'd want to make sure they understood before you walked them through this module? Write it down.

Teaching someone else is one of the most powerful ways to consolidate your own learning. If you can explain the why behind each configuration step — not just the how — you're in a strong position to support others.
