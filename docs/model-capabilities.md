# Model capability taxonomy

WaveSpeed model IDs are not a stable capability contract. This bundle derives selection metadata from each model's live JSON Schema and keeps the raw schema available for details.

## Task families

| Type | Best fit |
| --- | --- |
| `text-to-video` | New scene from a prompt; cheapest way to prototype motion and composition. |
| `image-to-video` | Animate a still, preserve a product/person/style, start/end frames, or multiple image references. |
| `video-to-video` | Prompt-guided edit, restyle, replace, transfer, or use an existing clip as motion/context. |
| `video-extend` | Continue an existing video in time. |
| `motion-control` | Transfer pose, camera path, or motion from a driving reference. |
| `digital-human` | Talking heads, avatars, lip sync, or person + audio/text generation. |
| `audio-to-video` | Visual generation driven primarily by audio. |
| `portrait-transfer` | Preserve or transfer identity/portrait behavior into video. |
| `video-effects` | Purpose-built transformation effects that still produce video. |
| `lora-support` | Video generation with one or more LoRA adapters; exact base task comes from the schema. |

## Input modalities

The same semantic input can appear under different field names. Examples include `image`, `images`, `reference_urls`, `video`, `input_video`, `ref_videos`, `audio`, and `audio_url`. `ModelDefinition::inputModalities()` inspects field names, descriptions, and uploader MIME hints. `ModelInputBuilder` maps only known, unambiguous roles and otherwise requires explicit raw inputs.

## Price semantics

`base_price_usd` supports coarse sorting only. The exact price must be requested with final inputs because providers commonly scale on duration, resolution, sound, output count, quality mode, and reference media. `Generator` always runs that exact preflight and can enforce a ceiling before submission.
