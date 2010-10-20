=== Recommended Reading: Google Reader Shared ===
Contributors: Jason Hoyt (Mendeley.com)
Tags: Mendeley, research, recommendations, reference manager, bibliography, widget
Requires at least: 2.8
Tested up to: 3.1
Stable tag: 0.1

Mendeley Related Research finds academic research related to your blog posts from http://mendeley.com.

== Description ==

Mendeley Related Research adds related academic literature citations to your blog posts. The research papers are pulled from Mendeley via the Open API and
includes social data, such as number of readers. The research pulled from Mendeley is based on the tags set in blog posts. Results are cached in your WordPress database for improved performance and API rate limit handling.

Mendeley is set to become the world's largest database for research papers. It currently holds more than 42M papers in every research discipline added by more than 550,000 researchers. Beyond just another database such as PubMed 
or Google Scholar, Mendeley includes social information to suggest the importance of each paper.


== Installation ==

1. Install with the WordPress plugin control panel or manually download the plugin and upload the folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Obtain a Mendeley develper API key by going to http://dev.mendeley.com/
4. Configure the plugin, such as number of items to display and adding your Mendeley API key, by going to the "Mendeley Papers" menu item under "Settings"
5. Widget users can add it to the sidebar by going to the "Widgets" menu under "Appearance" and adding the "Mendeley Papers" widget
6. The CSS file for editing the display colors is located in the `/wp-content/plugins/mendeleyRelatedResearch/css` directory

== Frequently Asked Questions ==

= What is the related research based on? =

It is based on the blog post tags. Therefore it is important that you add tags in the 'Post Tags' section when you create a new blog post.

= How can I improve the quality of papers that are shown? =

It is recommended to use at least two tags, but not more than seven. Additionally, the tags should be descriptive.

= How do I obtain a Mendeley developer API key? =

Go to http://dev.mendeley.com/

= I changed the number of items to be shown, but the display is still showing the previous setting. What is wrong? =

This is most likely due to the caching. It is set to 12 hours by default. You can either wait for it to expire or clear the cache by going to 'Settings' -> 'Mendeley Papers' in the WordPress admin panel.

== Screenshots ==

1. Sceenshot of side widget, with research papers related to blog entry.
2. Screenshot of settings panel in admin.


== Changelog ==

=v0.1= Release version (20-Oct-2010)