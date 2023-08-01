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


GetBodyContentViewHelper
========================

Extract the body tag content of the mail html content. Used to embed the mail html content.

Usage:
------

.. code-block:: html

    {mail.htmlContent -> mail:getBodyContent()}

RemoveMailBoundariesViewHelper
==============================

Remove the mail boundaries from the mail plain content. Used to embed the mail plain text content.

Usage:
------

.. code-block:: html

    {mail.plainContent -> mail:removeMailBoundaries()}



ConstantViewHelper
==================

Usage:
------

.. code-block:: html

    <f:variable name="contentSelectionBoundary" value="{mail:constant(name:'CONTENT_SECTION_BOUNDARY')}"/>
	<!--{contentSelectionBoundary}_-->Content for all<!--{contentSelectionBoundary}_END-->
	<!--{contentSelectionBoundary}_1-->content for category 1<!--{contentSelectionBoundary}_END-->
	<!--{contentSelectionBoundary}_1,2,3-->content for category 1, 2 and 3<!--{contentSelectionBoundary}_END-->

Parameters:
-----------

name:
~~~~~

Name of the constant

classFQN:
~~~~~~~~~

Full qualified name of the class containing the constants. Default: MEDIAESSENZ\Mail\Constants

Returns:
--------
The value of the constant if exists, otherwise null.
