# Making a course video

How to turn a course's video script into a narrated, captioned video. This is step 1
("Record") of [stage 8](stages/08-publish.md). It replaces recording a presenter, but
you can still record a human instead.

Claude does most of the work. You choose a voice and a look, watch the result, and
click one Export button.

## What you need

- **A finished script.** This is `NN-video-script.md` from stage 3d. Each row of its
  on-screen/voiceover table becomes one scene of the video.
- **An ElevenLabs key.** We use the team's nonprofit account. Save the key once, in
  PowerShell, then restart VS Code:

  ```powershell
  $k = Read-Host 'Paste your ElevenLabs API key' -AsSecureString
  [Environment]::SetEnvironmentVariable('ELEVENLABS_API_KEY', [Net.NetworkCredential]::new('', $k).Password, 'User')
  ```

  Never paste the key into a chat or a file. This repository is public.
- **Claude Motion.** It is available on our Claude plan, as the Motion artifact type.
- **ffmpeg** installed on your computer.

## Where the files go

Audio and video are large, and they **never go in git**. Everything for a course lives in
an `ltct-video` folder **beside** the repo, not inside it. For a clone at
`C:\projects\virtual-ltct`, that is `C:\projects\ltct-video\<course>\`. The scripts refuse
any folder inside a git repository.

Save Motion's export into that folder too, never into the course folder.

## The steps

1. **Split the script into scenes.**
   `python scripts/video_scenes.py --slug <course> --script NN-video-script.md`
   This writes `scenes.json` and reports each scene's estimated narration time.
   - If a scene is flagged **needs a human**, its words may include text in a local
     language. Text-to-speech must not read it aloud. Record that line yourself and save
     it as `audio/<scene>.mp3`.
2. **Choose a voice.** Run
   `python scripts/video_narrate.py --slug <course> --list-voices`.
   Ask Claude to voice the cold open in two or three of them, then pick one by ear.
3. **Narrate.**
   `python scripts/video_narrate.py --slug <course> --voice <voice id>`
   This makes one audio file per scene and records each one's length.
   - Running it again only re-voices scenes whose wording changed, so fixing one line
     costs one line of credits.
4. **Build the film in Motion.** Ask Claude to make a Motion film from `scenes.json`, with
   each scene as long as its narration plus about half a second.
   - Claude asks you for a style and colors.
   - The film uses the course's real screenshots from `assets/`, never redrawn UI.
   - It has no music, so the narration has nothing to compete with.
   - Claude writes `film-timing.json` with each scene's length.
5. **Review.** Watch the film on its page and comment on any moment you want changed.
   - The page is **silent**. You only hear the voice in step 7's file.
6. **Export.** Click **Export** on the film's page and save the MP4 into the working
   folder.
7. **Add the narration and captions.**
   `python scripts/video_assemble.py --slug <course> --film "<exported>.mp4"`
   This writes `<course>.mp4` with the voice laid on, and `<course>.srt` captions.
   - Watch it through. If voice and picture drift apart, comment on the film, ask Claude
     to retime that scene, re-export and run this step again.
8. **Upload** the MP4 and its `.srt` to Vimeo or Drive, then carry on with stage 8's
   step 2.

## Rules that still hold

- **The script is the source.** To change what is said, edit `NN-video-script.md`, then
  re-run steps 1, 3 and 7. Never fix the wording only in Motion or only in the audio.
- **No language data spoken by AI.** See "Who an LTC is" in `CLAUDE.md`. Screenshots are
  shown, never read aloud.
- **Captions always ship with the video.** Video is never the only route to the content
  (`INTENT.md`).
