.. include:: /Includes.rst.txt

.. _pitfalls:

========
Pitfalls
========

Wrong Entry Point
-----------------

This extension needs an entry point with a protokoll and domain to proper
fetch internal pages.

An entry point with just a slash ("/") will not work!

This value can be set here "Sites Module" > "Edit site" > "General" > "Entry Point".

If you e.g. use the introduction package, this value is set to "/" by default!


Self Signed Certificates
------------------------

If you use a self signed certificate, you should also check, that "HTTP" > "verify"
is set to "0".

This can be done under "Admin Tools" > "Settings" > "Configure Installation Wide Options"
> Filter by "verify" > enter "0" > Press "Write configuration"


Jumpurl Error Messages
----------------------

The current version of jumpurl found in ter (8.0.3) has some php 8 issues:

   - https://github.com/FriendsOfTYPO3/jumpurl/issues/33
   - https://github.com/FriendsOfTYPO3/jumpurl/issues/36
   - https://github.com/FriendsOfTYPO3/jumpurl/issues/37

There is already a pending pull request, which unfortunately stuck in that state
since a while:

   - https://github.com/FriendsOfTYPO3/jumpurl/pull/35

To help your self out, you could download the zip of the pull request repo, which
contains the necessary fixes under this url:

   - https://github.com/woodyc79/jumpurl_t3v11/archive/refs/heads/master.zip

After downloading, rename the master.zip to jumpurl_8.0.3.zip and upload it within
the extension manager.
