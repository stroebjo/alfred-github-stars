GitHub Stars Alfred Workflow
============================

This workflow for [Alfred](http://www.alfredapp.com/) allows you to search through your GitHub stars.

Search through your stars with `ghs <keyword>`. Than just hit enter on a result to directly open the repository in your browser or use to modifier key `cmd` to copy the URL to the clipboard. You can also use `ctrl` to copy the `git clone` command to the clipboard.

Installation
------------

[Download the Workflow here](https://github.com/stroebjo/alfred-github-stars/releases). Then type this command into Alfred to set your GitHub username: `ghsset user <username>`.

I used the public GitHub API wich currently limits anonymous request to 60 calls per hour. As of now the workflow features a simple cache, the GitHub Stars will be cached for 24 hours after the first request. You can change the time inside of the `app.php`.

Credits
-------

Is was inspired to create this workflow after seeing Adam Simpsons [workflow to filter through StackOverflow favorties](https://github.com/asimpson/stackoverflow-favorites-alfred-workflow).

