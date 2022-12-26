.. include:: /Includes.rst.txt

.. _dependencies:

============
Dependencies
============

MAIL make use of several packages all found at packagist.org:
 - friendsoftypo3/tt-address (https://github.com/friendsoftypo3/tt_address)
 - friendsoftypo3/jumpurl (https://github.com/friendsoftypo3/jumpurl)
 - typo3/cms-redirects (https://github.com/TYPO3/typo3/tree/main/typo3/sysext/redirects)
 - scssphp/scssphp (https://github.com/scssphp/scssphp)
 - pelago/emogrifier (https://github.com/MyIntervals/emogrifier)
 - league/html-to-markdown (https://github.com/thephpleague/html-to-markdown)
 - tburry/pquery (https://github.com/tburry/pquery)
 - tedivm/fetch (https://github.com/tedious/Fetch)

Kudos to all involved coders who put her love and energy in it. Without her, this extension would not exist.

Here comes a brief explanation of what each package is used for:

friendsoftypo3/tt-address
=========================

Store mail recipients

friendsoftypo3/jumpurl
======================

Used for click tracking

typo3/cms-redirects
======================

Used for redirects of shorted links in plain text mails

scssphp/scssphp
===============

This package transpiles scss files to css, which makes it possible to use the scss files of foundation mail and change there values (colors, dimensions) using typoscript constants.
I had to modify the original scss from foundation a little, because the scssphp package could currently not handle sass modules like math, which were used on some places.
See https://github.com/scssphp/scssphp/issues/421 for more information
In the end, I just replaced all `math.div()` with `/`.

pelago/emogrifier
================

This package is needed to convert all css to inline styles, which is unfortunately necessary for outlook and co.

league/html-to-markdown
=======================

This package is used to convert an html mail to a plain text (markdown) version using a middleware, just by adding `?plain=1`to the url.
It has the ability to add own converters, which is used by this extension to handle the mail boundaries, which wrapped around every content element.

tburry/pquery
=============

This package is used on several places to extract specific parts of a mail (e.g. links, images, special data attributes).

tedivm/fetch
============

This package is used by the AnalyzeBounceMailCommand to read the mail account of returned mails.
