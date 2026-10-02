<?php
/**
 * Board column templates for Kanban (PHP registry; deploy to change).
 * Seed cards use per-column pools; each new board picks a random subset per column,
 * with at least one column getting at least 5 cards when the pool has enough options.
 */

if (!defined('CG_KANBAN_BOARD_TEMPLATES_LOADED')) {
    define('CG_KANBAN_BOARD_TEMPLATES_LOADED', true);
}

/** First auto-created board for a new project uses this slug */
const CG_KANBAN_DEFAULT_FIRST_BOARD_TEMPLATE_SLUG = 'commercial_ads';

const KANBAN_TEMPLATE_LINK_CINEGRID = 'https://cinegrid.net';

/**
 * @return array<string, mixed>
 */
function kanban_board_templates_definitions(): array {
    return [
        'blank' => [
            'slug' => 'blank',
            'label' => 'Classic',
            'description' => 'To Do, Doing, Done — simple default.',
            'default_board_name' => 'New Board',
            'columns' => ['To Do', 'Doing', 'Done'],
            'seed_pools' => [],
        ],
        'commercial_ads' => [
            'slug' => 'commercial_ads',
            'label' => 'Commercial / ads',
            'description' => 'Pre-production through delivery for commercial and ad work.',
            'default_board_name' => 'Commercial project',
            'columns' => [
                'Pre-production',
                'Recce',
                'Budget breakdown',
                'Production',
                'Post-production',
                'Delivery',
            ],
            'seed_pools' => [
                [
                    ['title' => 'Creative brief & client goals', 'description' => 'Brand message, audience, must-haves, and deadlines.'],
                    ['title' => 'Script / storyboard sign-off', 'description' => 'Locked script, frame storyboard, client approval.'],
                    ['title' => 'Mood board & references', 'description' => 'Look, pacing, color, and competitor refs.'],
                    ['title' => 'Casting & talent', 'description' => 'Talent options, contracts, wardrobe notes.'],
                    ['title' => 'Master schedule', 'description' => 'Key milestones from kickoff to delivery.'],
                    ['title' => 'Client kickoff deck', 'description' => 'Stakeholders, approvals chain, brand guidelines.'],
                    ['title' => 'Legal & product claims', 'description' => 'Disclaimers, substantiation, regulatory notes.'],
                ],
                [
                    ['title' => 'Location shortlist', 'description' => 'Compare options with pros/cons.'],
                    ['title' => 'Site photos & measurements', 'description' => 'Sun path, noise, power, rigging points.'],
                    ['title' => 'Permits & access', 'description' => 'Filming permits, building access, insurance certs.'],
                    ['title' => 'Parking & unit base', 'description' => 'Crew parking, equipment load-in route.'],
                    ['title' => 'Backup locations', 'description' => 'Plan B if weather or access fails.'],
                    ['title' => 'Noise & sound survey', 'description' => 'Ambient levels, generators, quiet hours.'],
                    ['title' => 'Drone / height restrictions', 'description' => 'Airspace, NOTAM, local rules.'],
                ],
                [
                    ['title' => 'Top-sheet overview', 'description' => 'High-level buckets vs total budget.'],
                    ['title' => 'Line items & vendor quotes', 'description' => 'Camera, grip, locations, talent, post.'],
                    ['title' => 'Contingency buffer', 'description' => 'Reserve for overages and pickups.'],
                    ['title' => 'Client budget approval', 'description' => 'Signed-off estimate and change-order rules.'],
                    ['title' => 'Payment schedule', 'description' => 'Deposits, milestones, final balance.'],
                    ['title' => 'Overtime assumptions', 'description' => '10+2, meal penalties if applicable.'],
                    ['title' => 'FX / music licensing line', 'description' => 'Stock, composer, needle drops.'],
                ],
                [
                    ['title' => 'Call sheet & shoot day plan', 'description' => 'Call times, scenes, contacts, safety.'],
                    [
                        'title' => 'Equipment checklist',
                        'description' => 'Camera, lenses, lights, audio, DIT.',
                        'links' => [KANBAN_TEMPLATE_LINK_CINEGRID],
                    ],
                    ['title' => 'Crew & department heads', 'description' => 'Phone list and chain of command.'],
                    ['title' => 'Shot list & coverage', 'description' => 'Scene-by-scene beats and alt takes.'],
                    ['title' => 'Catering & safety', 'description' => 'Meals, first aid, COVID/safety if needed.'],
                    ['title' => 'Data wrangling plan', 'description' => 'Offload, proxies, checksum workflow.'],
                    ['title' => 'Art department run-through', 'description' => 'Props, set dec, last looks.'],
                ],
                [
                    ['title' => 'Rough cut & structure', 'description' => 'First assembly and story pass.'],
                    ['title' => 'VFX / GFX / titles', 'description' => 'Track shots, supers, end frames.'],
                    ['title' => 'Sound design & mix', 'description' => 'Dialogue, SFX, music stems, legal clearance.'],
                    ['title' => 'Color grade', 'description' => 'Look development and final pass.'],
                    ['title' => 'Client review rounds', 'description' => 'Feedback log and version naming.'],
                    ['title' => 'Captions & subtitles', 'description' => 'Languages, forced narrative, compliance.'],
                    ['title' => 'Alternate cuts', 'description' => '15s, 6s, social safe zones.'],
                ],
                [
                    ['title' => 'Master specs', 'description' => 'Resolution, codec, frame rate, loudness.'],
                    ['title' => 'Deliverables list', 'description' => 'Hero cut, cutdowns, social ratios, stills.'],
                    ['title' => 'Upload & handoff', 'description' => 'WeTransfer, frame.io, or drive links.'],
                    ['title' => 'Archive & project backup', 'description' => 'RAW, project files, and LTO if applicable.'],
                    ['title' => 'Invoice & wrap', 'description' => 'Final billing and thank-you / case study.'],
                    ['title' => 'As-run & compliance', 'description' => 'Clearance packet for broadcaster if needed.'],
                    ['title' => 'Case study assets', 'description' => 'BTS, stills, testimonial for portfolio.'],
                ],
            ],
        ],
        'wedding_photography' => [
            'slug' => 'wedding_photography',
            'label' => 'Wedding photography',
            'description' => 'Inquiry through delivery for wedding shoots.',
            'default_board_name' => 'Wedding shoot',
            'columns' => [
                'Inquiry',
                'Contract',
                'Shot list',
                'Shoot day',
                'Editing',
                'Delivery',
                'Archive',
            ],
            'seed_pools' => [
                [
                    ['title' => 'Lead intake', 'description' => 'Names, wedding date, venue, contact.'],
                    ['title' => 'Consultation call', 'description' => 'Style, hours, second shooter, expectations.'],
                    ['title' => 'Package & pricing', 'description' => 'Hours, deliverables, travel, add-ons.'],
                    ['title' => 'Hold the date', 'description' => 'Calendar block and follow-up timeline.'],
                    ['title' => 'Referrals & portfolio', 'description' => 'Send galleries similar to their vibe.'],
                    ['title' => 'Engagement session', 'description' => 'Optional session, location ideas.'],
                    ['title' => 'Rain / backup plan', 'description' => 'Indoor options, tent, timeline buffer.'],
                ],
                [
                    ['title' => 'Contract & terms', 'description' => 'Services, payment, cancellation, IP.'],
                    ['title' => 'Deposit invoice', 'description' => 'Signed agreement + first payment.'],
                    ['title' => 'Insurance / permits', 'description' => 'Venue requirements, COI if needed.'],
                    ['title' => 'Timeline questionnaire', 'description' => 'Full-day schedule from prep to exit.'],
                    ['title' => 'Vendor list', 'description' => 'Planner, video, DJ — coordination contacts.'],
                    ['title' => 'Family politics notes', 'description' => 'Sensitive dynamics, divorced parents, VIPs.'],
                    ['title' => 'Meal count & dietary', 'description' => 'Vendor meals, photographer headcount.'],
                ],
                [
                    ['title' => 'Must-have family formals', 'description' => 'Groupings and special combinations.'],
                    ['title' => 'Ceremony coverage', 'description' => 'Processional, vows, ring exchange, recessional.'],
                    ['title' => 'Couple portraits', 'description' => 'Locations, golden hour, rain backup.'],
                    ['title' => 'Reception details', 'description' => 'Decor, cake, speeches, first dance.'],
                    ['title' => 'VIP people list', 'description' => 'Who cannot be missed in candids.'],
                    ['title' => 'Religious / cultural moments', 'description' => 'Specific rites and restrictions.'],
                    ['title' => 'Send-off / exit plan', 'description' => 'Sparklers, car, timeline to end.'],
                ],
                [
                    ['title' => 'Prep & getting ready', 'description' => 'Details, dress, candids, timeline check.'],
                    ['title' => 'Ceremony run-of-show', 'description' => 'Angles, audio sync, house rules.'],
                    ['title' => 'Group & family photos', 'description' => 'Efficient list, MC or planner assist.'],
                    ['title' => 'Reception events', 'description' => 'Entrances, toasts, dances, exit.'],
                    ['title' => 'Memory cards & backup', 'description' => 'Dual slots, offload plan, spare batteries.'],
                    ['title' => 'Second shooter brief', 'description' => 'Split coverage, key moments.'],
                    ['title' => 'Venue lighting scout', 'description' => 'Tungsten, LED, flash plan.'],
                ],
                [
                    ['title' => 'Cull & selects', 'description' => 'Story set, duplicates out, favorites tagged.'],
                    ['title' => 'Color correction', 'description' => 'Consistent look across lighting scenarios.'],
                    ['title' => 'Retouching requests', 'description' => 'Skin, distractions — scope with package.'],
                    ['title' => 'Album design', 'description' => 'If included: spreads and revision rounds.'],
                    ['title' => 'Sneak peeks', 'description' => 'Quick social set if promised.'],
                    ['title' => 'Black & white set', 'description' => 'Optional artistic pass.'],
                    ['title' => 'Print sharpening', 'description' => 'Export profiles for lab.'],
                ],
                [
                    ['title' => 'Online gallery', 'description' => 'Password, download, print store link.'],
                    ['title' => 'High-res delivery', 'description' => 'ZIP or Pixieset — expiry date noted.'],
                    ['title' => 'Prints / album order', 'description' => 'Lab choice, shipping, proof approval.'],
                    ['title' => 'Final payment', 'description' => 'Balance due before full release if terms say so.'],
                    ['title' => 'Review & testimonial', 'description' => 'Ask for Google review when happy.'],
                    ['title' => 'Social sharing guide', 'description' => 'Tags, photo credit, crop tips.'],
                    ['title' => 'Thank-you to vendors', 'description' => 'Cross-post and referrals.'],
                ],
                [
                    ['title' => 'RAW archive', 'description' => 'Cold storage policy and retention length.'],
                    ['title' => 'Project backup', 'description' => 'Catalog, drives, cloud redundancy.'],
                    ['title' => 'License & usage', 'description' => 'Portfolio rights, vendor submissions.'],
                    ['title' => 'CRM / bookkeeping', 'description' => 'Tag won job, actual hours, profit notes.'],
                    ['title' => 'Anniversary marketing', 'description' => 'Optional email for prints next year.'],
                    ['title' => 'Gear maintenance log', 'description' => 'Clean sensors, calibrate after heavy season.'],
                    ['title' => 'Year-end portfolio cull', 'description' => 'Best work for site refresh.'],
                ],
            ],
        ],
        'music_video' => [
            'slug' => 'music_video',
            'label' => 'Music video',
            'description' => 'Treatment through master delivery.',
            'default_board_name' => 'Music video',
            'columns' => [
                'Concept',
                'Pre-pro',
                'Rehearsal',
                'Shoot',
                'Offline edit',
                'Color & sound',
                'Master',
            ],
            'seed_pools' => [
                [
                    ['title' => 'Song & rights check', 'description' => 'Master use, label, splits, rough cut length.'],
                    ['title' => 'Creative treatment', 'description' => 'Narrative, visual metaphors, tone.'],
                    ['title' => 'Reference pack', 'description' => 'Films, color, pacing, performance style.'],
                    ['title' => 'Budget & feasibility', 'description' => 'Match ambition to days and locations.'],
                    ['title' => 'Pitch / director’s treatment', 'description' => 'PDF or deck for artist / label.'],
                    ['title' => 'Mood film / animatic', 'description' => 'Temp edit to sell the idea.'],
                    ['title' => 'Explicit content / platform rules', 'description' => 'YouTube age gating, broadcast standards.'],
                ],
                [
                    ['title' => 'Shot list & storyboard', 'description' => 'Frame-by-frame intent and props.'],
                    ['title' => 'Locations locked', 'description' => 'Contracts, load-in, power, parking.'],
                    ['title' => 'Cast & wardrobe', 'description' => 'Extras, styling, continuity notes.'],
                    ['title' => 'Equipment order', 'description' => 'Camera package, lighting, playback on set.'],
                    ['title' => 'Production schedule', 'description' => 'Hour-by-hour with buffers.'],
                    ['title' => 'Stunt / safety plan', 'description' => 'Coordinator, pads, medic if needed.'],
                    ['title' => 'Playback speaker spec', 'description' => 'Wattage, sync to timecode if used.'],
                ],
                [
                    ['title' => 'Choreo / blocking', 'description' => 'Marks, camera path, safety.'],
                    ['title' => 'Lip-sync & playback', 'description' => 'Temp track, in-ear, crowd energy.'],
                    ['title' => 'Lighting rehearsal', 'description' => 'Key looks per setup.'],
                    ['title' => 'Wardrobe & hair test', 'description' => 'Final look on camera.'],
                    ['title' => 'Notes for shoot day', 'description' => 'What still needs tightening.'],
                    ['title' => 'Camera height & lens tests', 'description' => 'Hero focal length per setup.'],
                    ['title' => 'Crowd wrangling plan', 'description' => 'Extras briefing, releases.'],
                ],
                [
                    ['title' => 'Call sheet & safety', 'description' => 'Contacts, nearest hospital, weather.'],
                    ['title' => 'Performance takes', 'description' => 'Slate, circle takes, alt angles.'],
                    ['title' => 'B-roll & inserts', 'description' => 'Cutaways, textures, transitions.'],
                    ['title' => 'DIT / media', 'description' => 'Checksums, drives, duplicate copies.'],
                    ['title' => 'Playback on set', 'description' => 'Review critical takes before wrap.'],
                    ['title' => 'Steadicam / gimbal path', 'description' => 'Obstacles, operator rest breaks.'],
                    ['title' => 'SFX cues on set', 'description' => 'Practical effects timing with playback.'],
                ],
                [
                    ['title' => 'Assembly edit', 'description' => 'Structure to track length.'],
                    ['title' => 'Fine cut & pacing', 'description' => 'Beat sync to music grid.'],
                    ['title' => 'Temp VFX / titles', 'description' => 'Placeholder graphics and comps.'],
                    ['title' => 'Label feedback rounds', 'description' => 'v1, v2, notes consolidated.'],
                    ['title' => 'Picture lock target', 'description' => 'Date for color and sound handoff.'],
                    ['title' => 'Lyric alignment check', 'description' => 'Word sync, censored version.'],
                    ['title' => 'Thumbnail frame grab', 'description' => 'Hero still for release.'],
                ],
                [
                    ['title' => 'Color grade', 'description' => 'Look bible, shot matching, grain.'],
                    ['title' => 'Sound design', 'description' => 'SFX, ambiences, whooshes.'],
                    ['title' => 'Mix & master audio', 'description' => 'Stems, loudness for platforms.'],
                    ['title' => 'Final VFX', 'description' => 'Clean-up, screen replays, beauty.'],
                    ['title' => 'Captions / lyrics', 'description' => 'CC, lyric video variant if needed.'],
                    ['title' => 'Instrumental / TV mix', 'description' => 'Alternate for broadcast.'],
                    ['title' => 'Atmos / spatial export', 'description' => 'If delivering immersive.'],
                ],
                [
                    ['title' => 'Master file export', 'description' => 'ProRes / high-bitrate per spec.'],
                    ['title' => 'Platform versions', 'description' => '16:9, 9:16, 1:1, safe margins.'],
                    ['title' => 'Thumbnail & metadata', 'description' => 'YouTube title, credits block.'],
                    ['title' => 'Delivery upload', 'description' => 'Label portal, WeTransfer, or drive.'],
                    ['title' => 'Project archive', 'description' => 'Project file, RAW, stems zipped.'],
                    ['title' => 'Premiere / credits crawl', 'description' => 'Final spelling of everyone.'],
                    ['title' => 'Release schedule', 'description' => 'Premiere date, DSP link alignment.'],
                ],
            ],
        ],
        'short_film' => [
            'slug' => 'short_film',
            'label' => 'Short film',
            'description' => 'Idea through festival and final delivery.',
            'default_board_name' => 'Short film',
            'columns' => [
                'Development',
                'Script & look',
                'Financing',
                'Pre-production',
                'Production',
                'Post-production',
                'Festival & delivery',
            ],
            'seed_pools' => [
                [
                    ['title' => 'Logline & synopsis', 'description' => 'One-liner, theme, audience.'],
                    ['title' => 'Director’s vision deck', 'description' => 'References, tone, why now.'],
                    ['title' => 'Core team roles', 'description' => 'Producer, AD, DP targets.'],
                    ['title' => 'Rights & IP', 'description' => 'Original story, option, life rights.'],
                    ['title' => 'Comparable films', 'description' => 'Budget and festival trajectory refs.'],
                    ['title' => 'Workshop / table read', 'description' => 'Early feedback on story.'],
                    ['title' => 'Target festivals & window', 'description' => 'Premiere strategy, eligibility.'],
                ],
                [
                    ['title' => 'Script drafts & polish', 'description' => 'Structure, dialogue passes.'],
                    ['title' => 'Shot list & coverage plan', 'description' => 'Scene-by-scene intent.'],
                    ['title' => 'Lookbook / mood', 'description' => 'Color, lensing, production design.'],
                    ['title' => 'Storyboard / previz', 'description' => 'Key sequences.'],
                    ['title' => 'Casting breakdown', 'description' => 'Roles, age range, special skills.'],
                    ['title' => 'Location script breakdown', 'description' => 'INT/EXT, time of day, VFX notes.'],
                    ['title' => 'Script clearance', 'description' => 'Brands, music mentions, likeness.'],
                ],
                [
                    ['title' => 'Budget top sheet', 'description' => 'Above / below the line overview.'],
                    ['title' => 'Grants & labs', 'description' => 'Deadlines, eligibility, deliverables.'],
                    ['title' => 'Crowd / private investors', 'description' => 'Pitch deck, revenue waterfall.'],
                    ['title' => 'Deferrals & points', 'description' => 'Crew deals, backend clarity.'],
                    ['title' => 'Insurance quote', 'description' => 'E&O, equipment, COI for locations.'],
                    ['title' => 'Completion bond?', 'description' => 'If required by financier.'],
                    ['title' => 'Tax incentive research', 'description' => 'Region, residency, audit trail.'],
                ],
                [
                    ['title' => 'Schedule & one-liner', 'description' => 'Shoot days vs scenes.'],
                    ['title' => 'Department heads hired', 'description' => 'Contracts, start dates.'],
                    ['title' => 'Location agreements', 'description' => 'Fees, hold harmless, parking.'],
                    ['title' => 'Rentals & purchase orders', 'description' => 'Camera, grip, art, G&E.'],
                    ['title' => 'Travel & housing', 'description' => 'Per diem, block rooms.'],
                    ['title' => 'SFX / stunts prep', 'description' => 'Tests, safety meeting.'],
                    ['title' => 'Table read with crew', 'description' => 'Final questions before shoot.'],
                ],
                [
                    ['title' => 'Dailies workflow', 'description' => 'Color temp, sound sync, review notes.'],
                    ['title' => 'Coverage vs shot list', 'description' => 'Pickups list for each day.'],
                    ['title' => 'Sound reports', 'description' => 'ISO tracks, wild lines.'],
                    ['title' => 'Continuity photos', 'description' => 'Wardrobe, props, makeup.'],
                    ['title' => 'Meals & turnaround', 'description' => 'Union rules, forced calls.'],
                    ['title' => 'Child / animal rules', 'description' => 'Hours, wrangler, AHA if needed.'],
                    ['title' => 'End of day reports', 'description' => 'Producer summary, spend vs plan.'],
                ],
                [
                    ['title' => 'Editor’s assembly', 'description' => 'First full timeline.'],
                    ['title' => 'Director’s cut', 'description' => 'Creative pass before notes.'],
                    ['title' => 'Composer / score', 'description' => 'Temp score, final stems.'],
                    ['title' => 'Sound edit & design', 'description' => 'BG, foley, ADR list.'],
                    ['title' => 'VFX shots', 'description' => 'Roto, comps, deliveries per shot.'],
                    ['title' => 'Color final', 'description' => 'Theatrical vs web trim.'],
                    ['title' => 'Credits & thank-yous', 'description' => 'Contracts spelling, logos order.'],
                ],
                [
                    ['title' => 'Festival strategy', 'description' => 'Tier list, fees, premiere rules.'],
                    ['title' => 'DCP / ProRes masters', 'description' => 'Theatrical vs streaming specs.'],
                    ['title' => 'Press kit & stills', 'description' => 'Synopsis, bios, high-res grabs.'],
                    ['title' => 'Subtitles & accessibility', 'description' => 'SRT, SDH, forced narrative.'],
                    ['title' => 'Deliverables to distributor', 'description' => 'M&E splits, legal docs.'],
                    ['title' => 'Vimeo / private screener', 'description' => 'Password, expiry, watermark.'],
                    ['title' => 'Awards FYC calendar', 'description' => 'Guilds, short categories.'],
                ],
            ],
        ],
        'documentary' => [
            'slug' => 'documentary',
            'label' => 'Documentary',
            'description' => 'Research through distribution for factual and long-form docs.',
            'default_board_name' => 'Documentary',
            'columns' => [
                'Research',
                'Development',
                'Production',
                'Post',
                'Outreach',
                'Distribution',
            ],
            'seed_pools' => [
                [
                    ['title' => 'Topic & thesis', 'description' => 'Central question, angle, audience.'],
                    ['title' => 'Archival sources', 'description' => 'Libraries, newsreels, rights holders.'],
                    ['title' => 'Interview wish list', 'description' => 'Subjects, experts, access strategy.'],
                    ['title' => 'Ethics & consent', 'description' => 'Release forms, vulnerable contributors.'],
                    ['title' => 'Fact-check workflow', 'description' => 'Claims, citations, legal review.'],
                    ['title' => 'Budget & schedule reality', 'description' => 'Years vs funding cycles.'],
                    ['title' => 'Comparable docs', 'description' => 'Style, length, festival path.'],
                ],
                [
                    ['title' => 'Treatment / deck', 'description' => 'Pitch for funders and partners.'],
                    ['title' => 'Outline & beats', 'description' => 'Story arc without locking edit.'],
                    ['title' => 'Shooting outline', 'description' => 'Scenes, vérité vs sit-down.'],
                    ['title' => 'Reenactment plan', 'description' => 'If any — casting, accuracy.'],
                    ['title' => 'Music & clearances plan', 'description' => 'Composer vs library vs sync.'],
                    ['title' => 'Advisory board', 'description' => 'Experts for sensitive topics.'],
                    ['title' => 'Sizzle / sample scene', 'description' => 'Proof of access and tone.'],
                ],
                [
                    ['title' => 'Field producing', 'description' => 'Travel, fixers, local crew.'],
                    ['title' => 'Interview setups', 'description' => 'Lighting, audio, B-roll coverage.'],
                    ['title' => 'Vérité / observational days', 'description' => 'Follow subjects, minimal footprint.'],
                    ['title' => 'Archival digitizing', 'description' => 'Scan specs, metadata, chain of custody.'],
                    ['title' => 'Pickups & holes', 'description' => 'List of missing coverage.'],
                    ['title' => 'Production stills', 'description' => 'Poster, press, EPK.'],
                    ['title' => 'Dailies & transcription', 'description' => 'Sync, log, searchable interviews.'],
                ],
                [
                    ['title' => 'Paper edit / selects', 'description' => 'String-out and themes.'],
                    ['title' => 'Rough assemblies', 'description' => 'Try alternate structures.'],
                    ['title' => 'Graphics & maps', 'description' => 'Lower thirds, archival captions.'],
                    ['title' => 'Composer & score', 'description' => 'Emotional arc, stems.'],
                    ['title' => 'Mix & M&E', 'description' => 'International delivery prep.'],
                    ['title' => 'Fact-check pass', 'description' => 'Final legal / compliance.'],
                    ['title' => 'Color & grain', 'description' => 'Unified look across sources.'],
                ],
                [
                    ['title' => 'Website & social', 'description' => 'Trailer, key art, hashtags.'],
                    ['title' => 'Festival submissions', 'description' => 'Deadlines, DCP vs file specs.'],
                    ['title' => 'Educational partners', 'description' => 'Screenings, discussion guides.'],
                    ['title' => 'Impact campaign', 'description' => 'NGOs, petitions, measurable goals.'],
                    ['title' => 'Press kit', 'description' => 'Bios, stills, director statement.'],
                    ['title' => 'Screening tour', 'description' => 'Q&A, travel, honoraria.'],
                    ['title' => 'Awards strategy', 'description' => 'Guilds, shortlist timing.'],
                ],
                [
                    ['title' => 'Sales agent / distro', 'description' => 'SVOD, educational, territory deals.'],
                    ['title' => 'Broadcast deliverables', 'description' => 'Loudness, captions, QC.'],
                    ['title' => 'SVOD / AVOD & TVOD', 'description' => 'Platform deals, rental windows, territory rights.'],
                    ['title' => 'Educational license', 'description' => 'Libraries, universities, pricing.'],
                    ['title' => 'Streaming window', 'description' => 'Holdbacks, exclusivity.'],
                    ['title' => 'Long-term archive', 'description' => 'Masters, LTO, cloud.'],
                    ['title' => 'Residuals & reports', 'description' => 'Revenue share, transparency.'],
                ],
            ],
        ],
        'corporate_brand' => [
            'slug' => 'corporate_brand',
            'label' => 'Corporate / brand',
            'description' => 'Internal comms, brand films, and explainers.',
            'default_board_name' => 'Brand project',
            'columns' => [
                'Brief',
                'Planning',
                'Production',
                'Post',
                'Review',
                'Delivery',
            ],
            'seed_pools' => [
                [
                    ['title' => 'Stakeholder goals', 'description' => 'Marketing, HR, exec sponsors.'],
                    ['title' => 'Key messages', 'description' => 'Talking points, CTA, tone.'],
                    ['title' => 'Brand guidelines', 'description' => 'Logo, fonts, colors, voice.'],
                    ['title' => 'Audience & channels', 'description' => 'Intranet, LinkedIn, event loop.'],
                    ['title' => 'Success metrics', 'description' => 'Views, completion, survey.'],
                    ['title' => 'Compliance & legal', 'description' => 'Claims, regions, disclaimers.'],
                    ['title' => 'Reference examples', 'description' => 'What client likes / avoids.'],
                ],
                [
                    ['title' => 'Creative treatment', 'description' => 'Concept, talent, locations.'],
                    ['title' => 'Script / interview guide', 'description' => 'Exec quotes, b-roll list.'],
                    ['title' => 'Schedule & locations', 'description' => 'Offices, factories, remote.'],
                    ['title' => 'Release forms', 'description' => 'Employees, customers on camera.'],
                    ['title' => 'Graphics package', 'description' => 'Lower thirds, end card, supers.'],
                    ['title' => 'Voiceover / teleprompter', 'description' => 'If scripted pieces.'],
                    ['title' => 'Accessibility plan', 'description' => 'Captions, audio description.'],
                ],
                [
                    ['title' => 'Film day run-of-show', 'description' => 'Who speaks when, room access.'],
                    ['title' => 'B-roll coverage', 'description' => 'Offices, products, culture.'],
                    ['title' => 'Interview lighting & audio', 'description' => 'Consistent look across execs.'],
                    ['title' => 'Screen capture / UI', 'description' => 'Product shots, app walkthrough.'],
                    ['title' => 'Photo stills for deck', 'description' => 'Parallel to video team.'],
                    ['title' => 'Data wrangling', 'description' => 'Backup before leaving site.'],
                    ['title' => 'On-site client sign-off', 'description' => 'Hero moments approved.'],
                ],
                [
                    ['title' => 'Rough cut', 'description' => 'Structure and pacing first pass.'],
                    ['title' => 'Brand graphics lock', 'description' => 'Approved supers and end frame.'],
                    ['title' => 'Music & sfx', 'description' => 'Licensed tracks, corporate-safe.'],
                    ['title' => 'Localization prep', 'description' => 'VO script export for translation.'],
                    ['title' => 'Alt lengths', 'description' => '60s, 30s, 15s cutdowns.'],
                    ['title' => 'Color match brand', 'description' => 'LUT / grade consistency.'],
                    ['title' => 'Legal text insert', 'description' => 'Footnotes, trademarks.'],
                ],
                [
                    ['title' => 'Internal review round', 'description' => 'Marketing + comms feedback.'],
                    ['title' => 'Legal & compliance review', 'description' => 'Claims, appearances, logos.'],
                    ['title' => 'Executive approval', 'description' => 'Single approver list.'],
                    ['title' => 'Revision log', 'description' => 'Version names, locked changes.'],
                    ['title' => 'Test audience?', 'description' => 'Employee preview, survey.'],
                    ['title' => 'Accessibility QC', 'description' => 'Captions accuracy, contrast.'],
                    ['title' => 'Final sign-off email', 'description' => 'PDF confirmation in thread.'],
                ],
                [
                    ['title' => 'Master exports', 'description' => 'ProRes, H.264, specs per channel.'],
                    ['title' => 'Intranet / DAM upload', 'description' => 'Tags, permissions, expiry.'],
                    ['title' => 'Social crops', 'description' => '1:1, 9:16, safe text.'],
                    ['title' => 'Handoff to localization', 'description' => 'Stems, subtitle files.'],
                    ['title' => 'Archive project', 'description' => 'Project, GFX, music licenses.'],
                    ['title' => 'Analytics handoff', 'description' => 'UTM, embed codes.'],
                    ['title' => 'Case study / portfolio', 'description' => 'Internal reuse permission.'],
                ],
            ],
        ],
        'live_event' => [
            'slug' => 'live_event',
            'label' => 'Live event',
            'description' => 'Concerts, conferences, and multicam shows.',
            'default_board_name' => 'Live event',
            'columns' => [
                'Prep',
                'Load-in',
                'Show',
                'Strike',
                'Post',
                'Deliverables',
            ],
            'seed_pools' => [
                [
                    ['title' => 'Venue tech scout', 'description' => 'Power, internet, rigging points.'],
                    ['title' => 'Camera plan', 'description' => 'Fixed, handheld, remote heads.'],
                    ['title' => 'Audio patch list', 'description' => 'FOH, stage splits, comms.'],
                    ['title' => 'Graphics & playback', 'description' => 'Lyrics, slates, sponsor loops.'],
                    ['title' => 'Run-of-show document', 'description' => 'Minute-by-minute.'],
                    ['title' => 'Crew call & parking', 'description' => 'Badges, load-in windows.'],
                    ['title' => 'Contingency plans', 'description' => 'Rain, power loss, medical.'],
                ],
                [
                    ['title' => 'Truck pack list', 'description' => 'Cases, cables, spares.'],
                    ['title' => 'Stage plot vs reality', 'description' => 'Mark camera positions.'],
                    ['title' => 'Fiber / SDI runs', 'description' => 'Lengths, converters, test pattern.'],
                    ['title' => 'Power distro check', 'description' => 'Amps, grounding, distro maps.'],
                    ['title' => 'Network & recorders', 'description' => 'ISO cams, NDI, EVS.'],
                    ['title' => 'Rehearsal block', 'description' => 'Artist walk, focus, exposure.'],
                    ['title' => 'Safety briefing', 'description' => 'Crowd barriers, cable ramps.'],
                ],
                [
                    ['title' => 'Show caller script', 'description' => 'Roll cues, breaks, encores.'],
                    ['title' => 'ISO recording check', 'description' => 'All sources rolling, timecode.'],
                    ['title' => 'Comms discipline', 'description' => 'Director, TD, audio.'],
                    ['title' => 'Live stream health', 'description' => 'Bitrate, backup encoder.'],
                    ['title' => 'Crowd mics & ambience', 'description' => 'Balance with board mix.'],
                    ['title' => 'Still photographer coord', 'description' => 'Don’t cross live shots.'],
                    ['title' => 'End-of-show backup', 'description' => 'Verify drives before strike.'],
                ],
                [
                    ['title' => 'Safe power-down', 'description' => 'Order of packing, labeled cases.'],
                    ['title' => 'Venue walkthrough', 'description' => 'Damage check, lost items.'],
                    ['title' => 'Gear return / rental clock', 'description' => 'Avoid late fees.'],
                    ['title' => 'Overtime report', 'description' => 'Actual vs bid hours.'],
                    ['title' => 'Expense receipts', 'description' => 'Parking, meals, per diem.'],
                    ['title' => 'Thank-you to venue', 'description' => 'Relationship for next gig.'],
                    ['title' => 'Hard drive handoff', 'description' => 'Courier, checksum manifest.'],
                ],
                [
                    ['title' => 'Multicam sync', 'description' => 'Timecode or audio sync.'],
                    ['title' => 'Line cut vs ISO build', 'description' => 'Director’s live mix starting point.'],
                    ['title' => 'Color match cams', 'description' => 'Shots from different angles.'],
                    ['title' => 'Audio sweetening', 'description' => 'FOH mix vs ISO balance.'],
                    ['title' => 'Highlight reel', 'description' => 'Short promo for client.'],
                    ['title' => 'Full show assembly', 'description' => 'If delivering long-form.'],
                    ['title' => 'Noise & artifact pass', 'description' => 'Low light, compression.'],
                ],
                [
                    ['title' => 'Deliverable specs', 'description' => '4K, 1080p, loudness.'],
                    ['title' => 'Chapter markers', 'description' => 'For VOD players and scrub navigation.'],
                    ['title' => 'Client portal upload', 'description' => 'frame.io, Dropbox.'],
                    ['title' => 'Archival master', 'description' => 'ProRes HQ or agreed mezzanine.'],
                    ['title' => 'Still exports', 'description' => 'Key frames for marketing.'],
                    ['title' => 'Invoice & wrap notes', 'description' => 'What went well / improve.'],
                    ['title' => 'Gear inspection', 'description' => 'Damage claims before next job.'],
                ],
            ],
        ],
        'youtube_creator' => [
            'slug' => 'youtube_creator',
            'label' => 'YouTube / creator',
            'description' => 'Episodic content, tutorials, and channel growth.',
            'default_board_name' => 'Channel project',
            'columns' => [
                'Ideas',
                'Pre-production',
                'Film',
                'Edit',
                'Packaging',
                'Publish',
            ],
            'seed_pools' => [
                [
                    ['title' => 'Content calendar', 'description' => 'Upload rhythm, seasons, trends.'],
                    ['title' => 'Video ideas backlog', 'description' => 'Title hooks, thumbnails sketch.'],
                    ['title' => 'Keyword & competitor scan', 'description' => 'SEO gaps, collab targets.'],
                    ['title' => 'Sponsor / ad eligibility', 'description' => 'Topics, brand safety.'],
                    ['title' => 'Series vs one-off', 'description' => 'Playlist structure.'],
                    ['title' => 'Community requests', 'description' => 'Comments, polls, Discord.'],
                    ['title' => 'Batch filming themes', 'description' => 'Shoot multiple in one day.'],
                ],
                [
                    ['title' => 'Script or bullet outline', 'description' => 'Hook in first 30s.'],
                    ['title' => 'Set / location prep', 'description' => 'Backdrop, sound treatment.'],
                    ['title' => 'Gear checklist', 'description' => 'Camera, mic, lights, B-roll cam.'],
                    ['title' => 'B-roll shot list', 'description' => 'Cover edits and pacing.'],
                    ['title' => 'Teleprompter / notes', 'description' => 'If talking head.'],
                    ['title' => 'Remote guest tech test', 'description' => 'Zoom, Riverside, audio.'],
                    ['title' => 'Thumbnail rough concept', 'description' => 'Before you shoot key pose.'],
                ],
                [
                    ['title' => 'Primary A-roll', 'description' => 'Main performance or tutorial.'],
                    ['title' => 'B-roll & inserts', 'description' => 'Screens, hands, environment.'],
                    ['title' => 'Audio priority', 'description' => 'Lav vs shotgun, room tone.'],
                    ['title' => 'Multiple takes', 'description' => 'Safety for tricky lines.'],
                    ['title' => 'Behind the scenes', 'description' => 'Shorts, Stories, Patreon.'],
                    ['title' => 'Backup media', 'description' => 'Dual record, swap cards.'],
                    ['title' => 'Vertical safety', 'description' => 'Framing for Shorts reuse.'],
                ],
                [
                    ['title' => 'Rough cut & pacing', 'description' => 'Retention curve, trims.'],
                    ['title' => 'Graphics & lower thirds', 'description' => 'Consistent channel look.'],
                    ['title' => 'Music & sfx', 'description' => 'Royalty-free, Artlist, etc.'],
                    ['title' => 'Jump cuts & zoom style', 'description' => 'Match channel aesthetic.'],
                    ['title' => 'Color & skin tones', 'description' => 'Consistent across episodes.'],
                    ['title' => 'End screen elements', 'description' => 'Subscribe, next video, links.'],
                    ['title' => 'Shorts cutdown', 'description' => 'Vertical teaser from long.'],
                ],
                [
                    ['title' => 'Final thumbnail design', 'description' => 'Face, text, contrast mobile.'],
                    ['title' => 'Title & description SEO', 'description' => 'Keywords first 100 chars.'],
                    ['title' => 'Chapters / timestamps', 'description' => 'Chapters in description.'],
                    ['title' => 'Pinned comment', 'description' => 'CTA, links, corrections.'],
                    ['title' => 'Cards & end screens', 'description' => 'Verified links.'],
                    ['title' => 'Playlist add', 'description' => 'Series continuity.'],
                    ['title' => 'Subtitles / captions', 'description' => 'SRT upload or auto-review.'],
                ],
                [
                    ['title' => 'Scheduled publish', 'description' => 'Peak audience time.'],
                    ['title' => 'Community post teaser', 'description' => 'Before go-live.'],
                    ['title' => 'Cross-post Shorts', 'description' => 'TikTok, Reels export.'],
                    ['title' => 'Newsletter / Discord ping', 'description' => 'Notify superfans.'],
                    ['title' => 'Analytics review window', 'description' => '48h CTR, retention graph.'],
                    ['title' => 'Sponsor deliverables', 'description' => 'Reporting links, screenshots.'],
                    ['title' => 'Next video tease', 'description' => 'End card verbal CTA.'],
                ],
            ],
        ],
    ];
}

function kanban_board_template_resolve_slug(?string $raw): string {
    $raw = trim((string)$raw);
    $defs = kanban_board_templates_definitions();
    if ($raw !== '' && isset($defs[$raw])) {
        return $raw;
    }
    return 'blank';
}

/** Public list for UI / API (no seed card payloads). */
function kanban_board_templates_list_for_api(): array {
    $out = [];
    foreach (kanban_board_templates_definitions() as $def) {
        $out[] = [
            'slug' => $def['slug'],
            'label' => $def['label'],
            'description' => $def['description'],
            'default_board_name' => $def['default_board_name'],
            'column_count' => count($def['columns']),
        ];
    }
    return $out;
}

/**
 * @return list<array{0:string,1:int}> [name, position]
 */
function kanban_board_template_columns_with_positions(string $slug): array {
    $slug = kanban_board_template_resolve_slug($slug);
    $cols = kanban_board_templates_definitions()[$slug]['columns'] ?? ['To Do', 'Doing', 'Done'];
    $rows = [];
    foreach ($cols as $i => $name) {
        $rows[] = [trim((string)$name) ?: 'Column', $i];
    }
    return $rows;
}

/**
 * Random subset of seed cards (flat list with column_index) for this template.
 * Each column picks a random count; at least one column gets ≥5 cards when any pool has ≥5 items.
 *
 * @return list<array<string,mixed>>
 */
function kanban_board_template_pick_seed_cards(string $slug): array {
    $slug = kanban_board_template_resolve_slug($slug);
    $def = kanban_board_templates_definitions()[$slug] ?? null;
    if (!is_array($def)) {
        return [];
    }
    $pools = $def['seed_pools'] ?? [];
    if (!is_array($pools) || $pools === []) {
        return [];
    }

    $numCols = count($def['columns'] ?? []);
    if ($numCols <= 0) {
        return [];
    }

    $counts = [];
    foreach ($pools as $i => $pool) {
        if (!is_array($pool)) {
            continue;
        }
        $c = count($pool);
        if ($c === 0) {
            continue;
        }
        $hi = min(6, $c);
        $lo = min(2, $hi);
        $counts[$i] = random_int($lo, $hi);
    }

    $eligibleCols = [];
    foreach ($pools as $i => $pool) {
        if (is_array($pool) && count($pool) >= 5) {
            $eligibleCols[] = $i;
        }
    }
    if ($eligibleCols !== []) {
        $chosenCol = $eligibleCols[array_rand($eligibleCols)];
        $maxTake = count($pools[$chosenCol]);
        $counts[$chosenCol] = max($counts[$chosenCol] ?? 0, 5);
        $counts[$chosenCol] = min($counts[$chosenCol], $maxTake);
    } else {
        $bestI = -1;
        $bestLen = 0;
        foreach ($pools as $i => $pool) {
            if (!is_array($pool)) {
                continue;
            }
            $len = count($pool);
            if ($len > $bestLen) {
                $bestLen = $len;
                $bestI = $i;
            }
        }
        if ($bestI >= 0 && $bestLen > 0) {
            $counts[$bestI] = max($counts[$bestI] ?? 0, min(5, $bestLen));
            $counts[$bestI] = min($counts[$bestI], $bestLen);
        }
    }

    $out = [];
    foreach ($pools as $i => $pool) {
        if (!is_array($pool) || count($pool) === 0) {
            continue;
        }
        $take = (int)($counts[$i] ?? 0);
        if ($take <= 0) {
            continue;
        }
        $keys = array_keys($pool);
        shuffle($keys);
        $picked = 0;
        foreach ($keys as $k) {
            if ($picked >= $take) {
                break;
            }
            $card = $pool[$k];
            if (!is_array($card)) {
                continue;
            }
            $card['column_index'] = (int)$i;
            $out[] = $card;
            $picked++;
        }
    }

    usort($out, static function ($a, $b) {
        return ((int)($a['column_index'] ?? 0)) <=> ((int)($b['column_index'] ?? 0));
    });

    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function kanban_board_template_seed_cards(string $slug): array {
    return kanban_board_template_pick_seed_cards($slug);
}

function kanban_board_template_default_board_name(string $slug): string {
    $slug = kanban_board_template_resolve_slug($slug);
    $name = kanban_board_templates_definitions()[$slug]['default_board_name'] ?? 'Board';
    return $name !== '' ? $name : 'Board';
}
