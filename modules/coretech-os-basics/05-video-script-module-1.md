# Video script

**Estimated runtime:** 7 minutes
**Companion lesson file:** 01-module-1.md

## Overview / cold open

A language worker emails you: "My computer isn't working." That's it — no detail, no error
message, just a stalled project and someone waiting on you. Before you ever touch a
keyboard, you need a mental model of what's actually happening under the hood, and a
process for narrowing "isn't working" down to something you can actually fix. This video
won't replace Module 1 — it's a map of how the pieces fit together before you go do the
hands-on work yourself.

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

## Call to action / close

Now go work through Module 1's activities on your own machine: open your task manager,
identify your exact OS version, and document your system specs. They're short, safe, and
reversible, and they're what make this material stick. Keep your learning journal as you
go — you'll build on these specs in Module 2.

## Notes for the presenter

- This is a survey/orientation video, not a substitute for the module — don't try to
  narrate every activity or scenario on screen; point learners to the written material for
  the hands-on work and journaling.
- If demoing Task Manager / Activity Monitor / System Monitor live, use your own machine
  and don't linger on unfamiliar process names — the point is scale ("dozens of
  processes"), not identifying every one.
- The "no universal best OS" segment references Module 1's Pastor John scenario (donated
  seven-year-old Windows 7 laptop, Ge'ez script needs, Linux-savvy relative) — don't solve
  it on screen, just point learners to work through it themselves.
