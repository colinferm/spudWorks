[spudWorks Magazine]
I want to build a Wordpress theme for a web magazine that focuses on fiction stories and pictures miniature painting and game recaps.

The visual theme should be dark as the games are mostly set in the 40k universe but the main content area and text should be easy to read. Use of the black and yellow hazard stripe as a motif would also not be out of place since a good deal of the content will be about the game Necromunda. However, this theme should not be so overwhelming that it comes off as just another fan site as works of fiction set in unique worlds will also be featured.

[Typography]
- The site should be designed using a sans-serif font except where a serif font would call out special content.
- Ensure the font looks clean and that italics, especially, stand out.
- A distinct design for the horizontal rule (hr) tag would also be nice, perhaps featuring a thin, hazard stripe design.
- Please pick interesting fonts beyond the usual Arial, Helvetica, Times, Courier batch. Please stay away from fixed width fonts.

[Dependencies]
- Except where otherwise mentioned, do not use plugins or existing code libraries. 
- Libraries that are used should be downloaded, PHP libraries into the lib/ directory and added to a composer file, JavaScript libraries added to the js/ directory.
- Please make this theme responsive. If Bootstrap is the best way to make this happen, please use it so long as it doesn't _look_ like just another Bootstrap theme.

[General Coding Style]
- All functions should be formatted as "function name(param, param) {".
- Variable and function names must always be camelCase without spaces.
- Tabs shall be used instead of spaces for indents.
- Code across multiple lines such as assignments of multiple variables must be left ragged. Do not use spaces to even up the assignments.
- CSS code should all be placed in the css/ directory and written in SCSS. The project is already configured to compile that.

[Home Page]
- This should be the front-page.php template.
- The home page should feature the site's name prominently with a floating navigation bar on which menus can be set from Wordpress' menu admin. 
- There should be at least two or three featured pieces at the top. Should a piece be pinned, it should stand alone at the very top of the page. 
- Below the featured content should be 10-12 more content items in different box shapes to give the page some visual interest.
-- Design ideas could include:
--- Text floating over the featured image, aligned center.
--- Text aligned left under the featured image.
-- Items that will be reused on a page should be abstracted and turned into templates saved into the templates/ directory.
- Items should feature at least two or three tags on top of the headline and (possible) secondary headline.
- Make a space for bylines but make the design flexible enough that bylines can be hidden.

[Category / Tag Pages]
- These should feature the tag name at the top.
- Otherwise these should use the non-featured content templates created for the home page.
- A mix of template types should be used in a tasteful way to add to the visual interest.

[General Content Page Requirements]
- Content pages should feature a readable content column that does not stretch wider than 720px.
- There should always be space to feature a featured image but the design should also be flexible enough that it doesn't feel missing if it's not there.
- Should have a design for pullquotes.
-- Pullquotes should be in a bold, serif font.
- Headlines should be in a serif font.
- Should have a lightbox feature.
-- Clicking on any image should bring the image up in as large a size as the viewport will allow.
-- The background should be dimmed to focus attention on the image.
-- hovering the cursor over the image should bring up subtle forward-backward buttons to navigate to the next/previous image within the content section.
--- Likewise, using the forward / backward buttons should do the same.
- There should always be an author box at the bottom that features the author photo, the name, and any extra bio text that might be available.
- Beneath the author information should be 3-6 additional stories to read, ideally related through the use of tags and/or categories.
-- Tags should take priority with content sharing more of the same tags being shown over more recent content.

[Fiction Template]
- Create a content template that focuses heavily on the written word. 
- Sub-headlines (h2, h3, h4) should be clean and distinctive.
- It should be clean and readable with no distracting sidebar content.
- It should feature an image design that stretches across the entire viewport if embedded into the content.
- Pagination on this template will be especially important and should be clean and at the bottom of every page but above any author information.

[Showcase Template]
- Create a template that will be much more about showcasing picture content. 
- It should allow for long essays but also feature many more in-line images and galleries of images.
- This template should feature a sidebar on which Wordpress sidebar items may be placed.

[Administration]
- All code related to administration function should be placed in admin/.
- Create hooks to: 
-- allow for the selection of which template to display content in as a select box on the edit post admin screen.
-- Allow for a secondary headline underneath the main headline.
-- This data should be stored as a meta field attached to the content and read at display time to determine which template to use.
- Obviously, enable the use of image thumbnails.
