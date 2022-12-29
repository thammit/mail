.. include:: /Includes.rst.txt

.. _view-helpers:

===========
ViewHelpers
===========

PreviewLinksViewHelper
======================

Usage:
------

.. code-block:: html

    <f:variable name="previewLinks" value="{mail:previewLinks(uid:uid,pageId:pageId)}"/>

Parameters:
-----------

uid:
~~~~

Uid of the mail page

pageId:
~~~~~~~

Page id of the mail-sys-folder containing the mail configuration `mod.web_modules.mail.sendOptions`.

Returns:
--------

Preview-links, title, languageUid and flag-icon-identifiers for every language and type (plain/html), grouped by types.
The types (plain and/or html) will be taken from the Page TSconfig parameter `mod.web_modules.mail.sendOptions`
from the given `pageId`, defined with e.g. the :ref:`mail configuration module under "Mail format (default)" <configure-default-settings>`.
