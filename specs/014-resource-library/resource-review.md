# Resource descriptions to review

Drafted 2026-10-06 from each linked page. These 55 need a person's eye: the page would not load for an automated fetch (often a bot screen that a browser gets past), it redirects elsewhere, or the description rests on the title alone. Fix entries in `resources.yaml`; delete this file when done.

Broken today per `scripts/check_resource_links.py`:

- BROKEN [SSL: CERTIFICATE_VERIFY_FAILED] certificate verify failed: certificate has expired (_ssl.c:1077)  https://codeasy.net/  (Learn C# online)
- BROKEN The read operation timed out  https://www.mcafee.com/en-us/antivirus/malware.html  (What is malware?)
- BROKEN 410  https://docs.google.com/document/d/12ZmXEZWhTuhFCLqoLD2hYoVl4MvHpcNIISmuXLFZWmQ/edit  (Nepals basic computer course)
- BROKEN 404  http://www.ascd.org/Publications/Books/Overview/What-If-Building-Students-Problem-Solving-Skills-Through-Complex-Challenges.aspx  (Building Students' Problem-Solving Skills Through Complex Challenges)
- BROKEN timed out  https://www.chiark.greenend.org.uk/~sgtatham/bugs.html  (A Humorous but true article on Bug Reporting (Simon Tatham))
- BROKEN 404  https://paratext.org/support/  (Paratext Support)

| Status | Title | URL | Note |
|---|---|---|---|
| moved | Claude prompting guide | https://docs.claude.com/en/docs/build-with-claude/prompt-engineering/overview | Redirects to https://platform.claude.com/docs/en/build-with-claude/prompt-engineering/overview (same content, new host). |
| moved | Computer Basic Parts | https://edu.gcfglobal.org/en/computerbasics/basic-parts-of-a-computer/1/ | Redirects to https://www.learnfree.org/en/computerbasics/basic-parts-of-a-computer/1/ (GCFGlobal rebranded); content not read. |
| moved | Dell Keyboard Troubleshooting | http://www.dell.com/support/article/us/en/19/sln305029/keyboard-usage-and-troubleshooting-guide?lang=en | Now at https://www.dell.com/support/kbdoc/en-us/000131432/keyboard-usage-and-troubleshooting-guide |
| moved | Github Issues | https://guides.github.com/features/issues/ | Redirected to https://docs.github.com/issues/tracking-your-work-with-issues/about-issues |
| moved | Keyman Unicode Keyboards | http://scripts.sil.org/cms/scripts/page.php?site_id=nrsi&id=KeymanKeyboardLinks | Now at https://scripts.sil.org/keymankeyboardlinks.html (archived page) |
| moved | Klient Slutech | http://www.klientsolutech.com/online-basic-computer-courses- | Redirected to https://klientsolutech.com/online-basic-computer-courses-learn-essential-computer-skills/ |
| moved | Learn C# online | https://codeasy.net/ | Redirects to https://codeeasy.io/, which failed to load (expired certificate); description from title only. |
| moved | Multi-Dictionary Formatter (MDF) | https://software.sil.org/shoebox/mdf/ | Now at https://software.sil.org/shoebox/multi-dictionary-formatter-mdf |
| moved | OpenOffice Linguistic Tools | https://software.sil.org/oolt/ | Redirects to https://software.sil.org/libreofficetools/ |
| moved | SIL Converters 4.0 | https://scripts.sil.org/cms/scripts/page.php?site_id=nrsi&id=enccnvtrs | Page states it is obsolete; new home is https://software.sil.org/silconverters/ |
| moved | ScriptSource - Info on the World's writing systems | https://www.scriptsource.org/ | Redirects to https://writingsystems.info/support/migrating-from-scriptsource/ |
| moved | Sourceforge (Another open-source programming community) | http://www.sourceforge.com | Redirects to https://sourceforge.net/ (same site, canonical domain); page content not read. |
| moved | What is problem solving? | https://www.mindtools.com/pages/article/newTMC_00.htm | Redirected to https://www.mindtools.com/a6tcgqp/what-is-problem-solving |
| moved | What is two factor authentication? | https://authy.com/what-is-2fa/ | Fetcher reported final URL https://www.authy.com/ (Authy homepage); verify the 2FA explainer is still there |
| ok | Advocacy Principles and Practices (video) | https://vimeo.com/showcase/7563286/video/458673671 | Only the showcase title was readable; description written from the given title. |
| ok | Bloom Library | http://bloomlibrary.org | Page is script-rendered; little content visible to the fetcher |
| ok | CompTIA A+ Certification Video for more advance users | https://www.youtube.com/watch?v=2eLe7uz-7CM | Only page chrome was readable; description written from the given title. |
| ok | LIFT standard | https://code.google.com/archive/p/lift-standard/ | Archive page loaded but project content was not visible to the fetcher; description from title |
| ok | Lexique Pro | https://software.sil.org/lexiquepro/ | Software discontinued |
| ok | Networking (video) | https://vimeo.com/showcase/7563286/video/458683787 | Only the showcase title was readable; description written from the given title. |
| ok | PrimerPro training videos | https://vimeo.com/showcase/3521179 | Page content minimal to the fetcher; description from title |
| ok | Technical Notes on SFM Database Import | https://software.sil.org/fieldworks/wp-content/uploads/sites/38/2016/10/Technical-Notes-on-SFM-Database-Import.pdf | PDF loaded but its text was not readable by the fetcher; description from title |
| unreachable | A Humorous but true article on Bug Reporting (Simon Tatham) | https://www.chiark.greenend.org.uk/~sgtatham/bugs.html | Connection reset on fetch; description written from the title only. |
| unreachable | Assessment Grid | https://rm.coe.int/CoERMPublicCommonSearchServices/DisplayDCTMContent?documentId=090000168045bb52 | Blocked automated fetch (Cloudflare/bot check); description written from title and url only; likely loads in a browser. |
| unreachable | BART | https://www.sil.org/resources/publications/tw/bart | Server returned 403 Forbidden to automated fetch; description written from the title only. |
| unreachable | Building Students' Problem-Solving Skills Through Complex Challenges | http://www.ascd.org/Publications/Books/Overview/What-If-Building-Students-Problem-Solving-Skills-Through-Complex-Challenges.aspx | Server returned 404 Not Found; description written from the title only. |
| unreachable | Common European Framework of Reference (CEFR) | https://www.coe.int/en/web/common-european-framework-reference-languages | Blocked automated fetch (Cloudflare/bot check); description written from title and url only; likely loads in a browser. |
| unreachable | Computational Lexicography | https://typecraft.org/tc2wiki/Computational_Lexicography | HTTP 525; description from title and url only |
| unreachable | How to change keyboard layouts in Windows 11 | https://windowsreport.com/keyboard-layout-windows-11/ | HTTP 403; description from title and url only |
| unreachable | How to improve your problems solving skills | https://www.topuniversities.com/blog/how-improve-your-problem-solving-skills | Server returned 403 Forbidden to automated fetch; description written from the title only. |
| unreachable | How to network? | https://www.wikihow.com/Network | Fetcher is blocked from wikihow.com; description written from title and url only. |
| unreachable | How to write good bug report? | https://musescore.org/en/node/309537 | Server returned 403 Forbidden to automated fetch; description written from the title only. |
| unreachable | Introducing RAMP | https://www.sil.org/resources/archives/43211 | HTTP 403 to the fetcher; description and type inferred from title and url only. |
| unreachable | Logos 4 Video Tutorials | https://wiki.logos.com/Logos_4_Video_Tutorials | Redirects to https://community.logos.com/Logos_4_Video_Tutorials, which returned 403; description written from the title only. |
| unreachable | Logos Bible Software Wiki | https://wiki.logos.com/Logos_Bible_Software_Wiki | Redirects to https://community.logos.com/Logos_Bible_Software_Wiki, which returned 403; description written from the title only. |
| unreachable | Nepals basic computer course | https://docs.google.com/document/d/12ZmXEZWhTuhFCLqoLD2hYoVl4MvHpcNIISmuXLFZWmQ/edit | Google returned 410 Gone; description written from the title only. |
| unreachable | Net Literacy | https://drive.google.com/file/d/1sVyGCUqFEVLMSbajZw8hnRr4anSkcn7A/view | HTTP 401; file needs sign-in or is not public. Description from title only. |
| unreachable | NetLiteracy.org | https://drive.google.com/open?id=1sVyGCUqFEVLMSbajZw8hnRr4anSkcn7A | Redirects to a Google sign-in page, so not publicly viewable; description written from the title only. |
| unreachable | Paratext Support | https://paratext.org/support/ | Server returned 404 Not Found; description written from the title only. |
| unreachable | Phonology Assistant 3 | http://lingtransoft.info/apps/phonology-assistant-3 | Server returned 403 Forbidden; description written from the title only. |
| unreachable | RapidWords.net (Rapid Dictionary Development) | http://rapidwords.net/ | HTTP 403; description from title and url only |
| unreachable | Request Help with Dictionary Conversion into FLEx | https://www.webonary.org/request-help-with-dictionary-conversion-into-flex/ | HTTP 403; description from title and url only |
| unreachable | SIL Dictionaries & Lexicography | https://www.sil.org/dictionaries-lexicography | HTTP 403; description from title and url only |
| unreachable | SIL Language Documentation | https://www.sil.org/language-culture-documentation/language-documentation | HTTP 403; description from title and url only |
| unreachable | SIL Language and Culture Archives | https://www.sil.org/resources/language-culture-archives | HTTP 403 to the fetcher; description written from title and url only. |
| unreachable | SIL Literacy and Education | https://www.sil.org/literacy-education | HTTP 403; description from title and url only |
| unreachable | SIL Literacy and Education (ILS) | https://sites.google.com/d/1WRbBXpgVYdTHgcS0jLOdXOSggI17FruM/p/1Kx_uPz4kEIYT2iMXVr7q8gNsonSGXzfJ/edit | Redirects to Google sign-in; this is an /edit link to a private site. Description from title only |
| unreachable | SIL Translation | https://www.sil.org/translation | Server returned 403 Forbidden to automated fetch; description written from the title only. |
| unreachable | SILAS (Smart Interactive Layout Assistant for Scripture) | http://lingtransoft.info/apps/silas-smart-interactive-layout-assistant-scripture | Blocked automated fetch (Cloudflare/bot check); description written from title and url only; likely loads in a browser. |
| unreachable | The Problem-Solving Process | https://asq.org/quality-resources/problem-solving | Server returned 403 Forbidden to automated fetch; description written from the title only. |
| unreachable | Webonary | https://www.webonary.org/ | Blocked automated fetch (Cloudflare/bot check); description written from title and url only; likely loads in a browser. |
| unreachable | What arer problem-solving skills and why are they important? | https://www.careerbuilder.com/advice/what-are-problemsolving-skills-and-why-are-they-important | Server returned 403 Forbidden to automated fetch; description written from the title only. |
| unreachable | What is malware? | https://www.mcafee.com/en-us/antivirus/malware.html | Fetch timed out; description from title and url only |
| unreachable | Windows 10 Hardware Troubleshooting | https://www.techrepublic.com/article/how-to-more-effectively-troubleshoot-hardware-issues-in-windows-10-with-device-managers-views/ | HTTP 403 to the fetcher; description written from title and url only. |
| unreachable | WordReference (Translating Concepts) | http://www.wordreference.com/ | Server refused automated fetch (HTTP 418); description written from title and url only. |
