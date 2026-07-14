# Video script

**Estimated runtime:** 15 minutes
**Companion lesson file(s):** 01-module-1.md, 02-module-2.md, 03-module-3.md

## Overview / cold open

A language worker emails you: "My computer isn't working." That's it — no detail, no error
message, just a stalled project and someone waiting on you. Before you ever touch a
keyboard, you need a mental model of what's actually happening under the hood, and a
process for narrowing "isn't working" down to something you can actually fix. That's what
this course builds. This video won't replace the three modules — it's a map of how the
pieces fit together before you go do the hands-on work yourself.

## Script

| On-screen | Voiceover / talking points |
| --- | --- |
| Title slide: "Operating Systems for Language Technology Consultants" | You don't need to be an IT specialist to support language workers well, but you do need enough OS knowledge to tell the difference between a hardware problem, an application problem, and a settings problem — and to configure a system so it actually works for the scripts and languages someone is using. That's the whole arc of this course: understand it, configure it, fix it. |
| Slide: "What does an OS actually do?" (three icons: hardware, applications, interface) | Think of the operating system as the bridge between the hardware sitting on someone's desk and the applications they actually use. It does three jobs: it manages hardware — the processor, memory, storage, keyboard, display, network; it runs and manages applications, keeping several open at once without letting them collide; and it provides the interface — the desktop, the menus, the settings panels you and the user both touch. |
| Slide: "Where problems come from" (three columns mapped to the same three jobs) | Every OS problem you'll ever troubleshoot traces back to one of those three jobs. A USB drive not recognised, a display that won't turn on — that's hardware management. A program that crashes or won't launch — that's application management. Can't find a file, a setting won't save, text shows up as boxes instead of characters — that's the user interface layer, and for language work, that third category is where you'll spend a lot of your time. |
| Screen-share: Task Manager / Activity Monitor / System Monitor open, Processes tab visible | Module 1 has you open your own task manager and just look — dozens of processes, most of which you don't recognise, all being juggled by the OS right now. That exercise is worth doing for real, not just watching: it's the fastest way to make "the OS manages resources" feel concrete instead of abstract. |
| Slide: "Three OS families" — Windows / macOS / Linux logos side by side | Next, the landscape you'll actually work in. Windows is still the dominant OS in most of the regions language technology consultants serve — broad software compatibility, familiar to most users, but it comes with licensing cost and, on newer versions, real hardware requirements. macOS stands out for font rendering and complex-script handling, which matters a lot for typography-heavy language work, but it only runs on Apple hardware, and that's a real cost barrier in a lot of contexts. Linux costs nothing to license and breathes new life into older hardware, but it asks more of the user technically, and some language software just doesn't run on it natively. |
| Slide: "There's no universal 'best' OS" — bullet list: user comfort / software needed / hardware available / budget / local support | There's no "best" OS — there's only the OS that fits a specific person's software needs, hardware, budget, and available support. Module 1 walks through a scenario with a donated seven-year-old laptop running Windows 7, a language worker who needs Ge'ez script support, and a tech-savvy relative who runs Linux. Work through that scenario yourself before moving on — the reasoning matters more than the "answer." |
| Screen-share: `msinfo32` (Windows) / About This Mac → System Report (macOS) / `lscpu`, `free -h`, `df -h` (Linux) | Whatever OS you recommend, you'll need to actually identify what's in front of you — not "I have Windows," but the specific version, processor, RAM, storage, and architecture. Every OS has a built-in tool for this: System Information on Windows, System Report on macOS, a handful of terminal commands on Linux. Module 1 has you document your own system's specs and then check them against real software requirements — that's a skill you'll use in almost every support conversation you have. |
| Slide: "Module 2 — Configuring for language work" | Once you know what you're working with, Module 2 is about making a system actually usable for language work. Three things matter most: fonts, input languages, and display. |
| Screen-share: software.sil.org/fonts homepage, then a document showing boxes/question marks where characters should render | Start with fonts. When the right font isn't installed, characters show up as empty boxes or question marks — and that blocks the work completely. A translator can't read source text, a literacy worker can't lay out materials. SIL fonts are your first recommendation here: they're free, built specifically for the scripts language workers use, and well maintained — Charis SIL and Andika for Latin script, Scheherazade New for Arabic, Abyssinica SIL for Ethiopic, and others for South and Southeast Asian scripts. Module 2 walks you through downloading and installing one yourself and testing that it actually renders correctly. |
| Screen-share: language/input settings panel, with the input-language indicator in the taskbar or menu bar highlighted | Fonts let people see a language. Input languages let them type it. This is a different setting from the display language of the menus — someone can have an English interface while typing fluently in Amharic or Arabic, switching back and forth with a keyboard shortcut. Module 2 has you add an input language yourself and practice switching, because when a user says "I'm typing but nothing makes sense," nine times out of ten they're just in the wrong input language, and knowing that instantly is what makes you useful on a support call. |
| Screen-share: Display settings — scaling slider, resolution dropdown, ClearType tuner (Windows) | Last piece: display. Native resolution, appropriate scaling, and — on Windows — running the ClearType tuner all affect how sharp and readable text is, and that matters more for language work than most people assume, because diacritics and combining marks need to be legible for hours of reading, not just glanced at. |
| Slide: "Module 3 — The six-step diagnostic framework" (numbered list 1–6) | Now the part you'll use the most: troubleshooting. Module 3 gives you a six-step framework — gather information, isolate the problem, check the obvious first, research and form a hypothesis, test one change at a time, then document what you did. That last point about changing one thing at a time is worth repeating: if you update drivers, install antivirus, and change five settings all at once and the problem goes away, you still don't know what actually fixed it. |
| Screen-share: split view — Task Manager/Activity Monitor sorted by CPU, next to a slide reading "Hardware or software?" | The framework also gives you a lens for the hardware-versus-software question that comes up constantly. Software problems tend to develop gradually or right after a change, and often clear up with a restart. Hardware problems tend to show up suddenly, happen consistently, and don't respond to a restart or reinstall — that's your signal to escalate rather than keep digging. |
| Slide: "Common patterns" — gradual slowdown / sudden slowdown after update / won't start / frequent freezing | Module 3 catalogues the problems you'll see most — slow performance, freezing, won't start, connectivity failures, display issues — each with a recognisable pattern and a short list of likely causes. You won't memorise all of it from this video, and you're not meant to; the point is knowing the framework exists so you reach for it instead of guessing randomly. |
| Slide: "Repair, reinstall, or escalate?" | Module 3 also covers the judgment calls: when reinstalling is actually worth it versus when it's overkill, when to hand a problem to local IT or a specialist, and how to talk to a worried user honestly when you're not sure yet what's wrong. Those decision-making scenarios — a slow laptop in rural Uganda, a laptop with multiple failing symptoms before a workshop, a laptop making a clicking noise — are where all three modules come together. |

## Call to action / close

Now go work through the three modules in order — Module 1 first if you haven't, then
Modules 2 and 3. Do the hands-on activities on your own machine; they're short, safe, and
reversible, and they're what make this material stick. Keep your learning journal as you
go, and bring your documented system specs, your scenario answers, and your final
reflection to your mentor for review before you consider this course complete.

## Notes for the presenter

- This is a survey/orientation video, not a substitute for the modules — don't try to
  narrate every activity or scenario on screen; point learners to the written material for
  the hands-on work and journaling.
- Define on screen the first time they're used: "input language" vs. "display language"
  (Module 2), and "isolate the problem" as a specific step, not just a general idea
  (Module 3) — these are easy to skim past in speech.
- The fonts segment benefits from an actual visual of boxes/question-mark rendering
  failure next to the corrected version — don't just describe it verbally.
- If demoing Task Manager / Activity Monitor / System Monitor live, use your own machine
  and don't linger on unfamiliar process names — the point is scale ("dozens of
  processes"), not identifying every one.
- Say "SIL fonts," "Charis SIL," "Scheherazade New," and "Abyssinica SIL" clearly and
  consider a text overlay — these are proper names presenters may not be used to
  pronouncing.
- This module does not yet have a separate scenario bank or mentor guide file — the
  scenario-based activities and mentor review live inside the module files themselves
  (heaviest in 03-module-3.md's assessment scenarios and closing mentor-review section).
  The call-to-action above reflects that; if a standalone scenario bank or mentor guide is
  added to this module later, this section should be revisited to point to it directly.
- Pacing: this script runs long for a single continuous take at conversational pace: if
  recording in one sitting, consider a short pause/breath after the Module 1 recap (system
  info row) and after the fonts/input/display run in Module 2, to keep energy up through
  the troubleshooting section.
