AP News Tiny Tiny RSS Plugin
============================
Overview
---------------------
AP News no longer provides RSS feeds.  This plugin provides virtual AP News feeds based upon JSON used by their website.

Installation
---------------------
1. Verify you're using Tiny Tiny RSS code from 2017-10-01 or later (specifically: [`0f0d6ca559`](https://git.tt-rss.org/git/tt-rss/commit/0f0d6ca55945edca137ffb37a17856b93f8c88d8))
2. Clone the repo to an **apnews** subdirectory in your **plugins.local** directory:

   `git clone https://github.com/supahgreg/ttrss-apnews.git apnews`

3. Enable the plugin @ Preferences / Plugins

Usage
---------------------
1. Browse to https://apnews.com and click one of the categories to get a URL like https://apnews.com/tag/apf-topnews
2. Use the URL, as-is, to subscribe in Tiny Tiny RSS
3. Optionally, subscribe using a multi-tag URL like https://apnews.com/tag/apf-topnews,apf-usnews
