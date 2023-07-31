.. include:: /Includes.rst.txt

.. highlight:: typoscript

.. _typoscript-settings:

==========
TypoScript
==========

.. _typoscript-page-view-settings:

Page View Settings
==================

All page view settings need to be prefixed with  :typoscript:`plugin.mail.view.page`.


.. only:: html

   .. contents:: Properties
      :depth: 1
      :local:

.. _ts-page-view-templates-root-path:

templatesRootPath
-----------------

.. confval:: templatesRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Templates/Mail/
   :Path: plugin.mail.view.page

   Path to template root for mail pages (FE)

.. _ts-page-view-partials-root-path:

partialsRootPath
----------------

.. confval:: partialsRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Partials/
   :Path: plugin.mail.view.page

   Path to partials root for mail pages (FE)

.. _ts-page-view-layouts-root-path:

layoutsRootPath
---------------

.. confval:: layoutsRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Layouts/
   :Path: plugin.mail.view.page

   Path to layouts root for mail pages (FE)

Content View Settings
=====================

All content view settings need to be prefixed with  :typoscript:`plugin.mail.view.content`.


.. only:: html

   .. contents:: Properties
      :depth: 1
      :local:

.. _ts-content-view-templates-root-path:

templatesRootPath
-----------------

.. confval:: templatesRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Templates/ContentElements/
   :Path: plugin.mail.view.content

   Path to template root for mail contents (FE)

.. _ts-content-view-partials-root-path:

partialsRootPath
----------------

.. confval:: partialsRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Partials/
   :Path: plugin.mail.view.content

   Path to partials root for mail contents (FE)

.. _ts-content-view-layouts-root-path:

layoutsRootPath
---------------

.. confval:: layoutsRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Layouts/
   :Path: plugin.mail.view.content

   Path to layouts root for mail contents (FE)

.. _ts-content-header-settings:

Header Settings
===============

All header settings need to be prefixed with  :typoscript:`plugin.mail.settings.header`.


.. only:: html

   .. contents:: Properties
      :depth: 1
      :local:

.. _ts-content-header-title:

title
-----

.. confval:: title

   :type: string
   :Default: Newsletter
   :Path: plugin.mail.settings.header

   Title of the mail header

.. _ts-content-header-image-src:

image.src
---------

.. confval:: src

   :type: string
   :Default: EXT:mail/Resources/Public/Images/Logo.png
   :Path: plugin.mail.settings.image

   Logo source

.. _ts-content-header-image-alt:

image.alt
---------

.. confval:: alt

   :type: string
   :Default: Logo
   :Path: plugin.mail.settings.image

   Logo alt text

.. _ts-content-header-image-width:

image.width
-----------

.. confval:: width

   :type: string
   :Default: 300
   :Path: plugin.mail.settings.image

   Logo width

.. _ts-content-header-image-height:

image.height
------------

.. confval:: height

   :type: string
   :Default:
   :Path: plugin.mail.settings.image

   Logo height

.. _ts-content-scss-settings:

SCSS Settings
=============

All scss settings need to be prefixed with  :typoscript:`plugin.mail.settings.scss`.


.. only:: html

   .. contents:: Properties
      :depth: 1
      :local:

.. _ts-content-scss-modification:

modifications
------------

.. confval:: modifications

   :type: string
   :Default: EXT:mail/Resources/Public/Scss/mail.scss
   :Path: plugin.mail.settings.scss

   Scss file to modify the default scss of foundation mail

.. _ts-content-scss-primary-color:

primary-color
-------------

.. confval:: primary-color

   :type: color
   :Default: #037eab
   :Path: plugin.mail.settings.scss

   Primary color, used with components that support the `.primary` class

secondary-color
---------------

.. confval:: secondary-color

   :type: color
   :Default: #777777
   :Path: plugin.mail.settings.scss

   Secondary color, used with components that support the `.secondary` class

success-color
-------------

.. confval:: success-color

   :type: color
   :Default: #3adb76
   :Path: plugin.mail.settings.scss

   Color to indicate a positive status or action, used with the `.success` class

warning-color
-------------

.. confval:: warning-color

   :type: color
   :Default: #ffae00
   :Path: plugin.mail.settings.scss

   Color to indicate a caution status or action, used with the `.warning` class

alert-color
-----------

.. confval:: alert-color

   :type: color
   :Default: #ec5840
   :Path: plugin.mail.settings.scss

   Color to indicate a negative status or action, used with the `.alert` class

light-gray
----------

.. confval:: light-gray

   :type: color
   :Default: #f3f3f3
   :Path: plugin.mail.settings.scss

   Color used for light gray UI items within Foundation

medium-gray
-----------

.. confval:: medium-gray

   :type: color
   :Default: #cacaca
   :Path: plugin.mail.settings.scss

   Color used for medium gray UI items within Foundation

dark-gray
---------

.. confval:: dark-gray

   :type: color
   :Default: #8a8a8a
   :Path: plugin.mail.settings.scss

   Color used for dark gray UI items within Foundation

black
-----

.. confval:: black

   :type: color
   :Default: #0a0a0a
   :Path: plugin.mail.settings.scss

   Color used for black ui items within Foundation

white
-----

.. confval:: white

   :type: color
   :Default: #fefefe
   :Path: plugin.mail.settings.scss

   Color used for white ui items within Foundation

pre-color
---------

.. confval:: pre-color

   :type: color
   :Default: #ff6908
   :Path: plugin.mail.settings.scss

   Code color (<pre>)

header-color
------------

.. confval:: header-color

   :type: color
   :Default: #444444
   :Path: plugin.mail.settings.scss

   Headlines color

global-font-color
-----------------

.. confval:: global-font-color

   :type: color
   :Default: #444444
   :Path: plugin.mail.settings.scss

   Text color

header-background-color
-----------------------

.. confval:: header-background-color

   :type: color
   :Default: #037eab
   :Path: plugin.mail.settings.scss

   Header background color

body-background
---------------

.. confval:: body-background

   :type: string
   :Default: $light-gray
   :Path: plugin.mail.settings.scss

   Body background color

container-background-color
--------------------------

.. confval:: container-background-color

   :type: string
   :Default: $white
   :Path: plugin.mail.settings.scss

   Container background color

footer-background-color
-----------------------

.. confval:: footer-background-color

   :type: string
   :Default: $light-gray
   :Path: plugin.mail.settings.scss

   Footer background color

global-width
------------

.. confval:: global-width

   :type: string
   :Default: 600px
   :Path: plugin.mail.settings.scss

   Container width

global-width-small
------------------

.. confval:: global-width-small

   :type: string
   :Default: 95%
   :Path: plugin.mail.settings.scss

   Container width (small screens)

global-gutter
-------------

.. confval:: global-gutter

   :type: string
   :Default: 20px
   :Path: plugin.mail.settings.scss

   Gutter for grid elements

global-gutter-small
-------------------

.. confval:: global-gutter-small

   :type: string
   :Default: $global-gutter
   :Path: plugin.mail.settings.scss

   Gutter for grid elements (small screens)

global-padding
--------------

.. confval:: global-padding

   :type: string
   :Default: 20px
   :Path: plugin.mail.settings.scss

   Global padding

global-margin
-------------

.. confval:: global-margin

   :type: string
   :Default: 16px
   :Path: plugin.mail.settings.scss

   Global margin

global-radius
-------------

.. confval:: global-radius

   :type: string
   :Default: 3px
   :Path: plugin.mail.settings.scss

   Global radius

global-rounded
--------------

.. confval:: global-rounded

   :type: string
   :Default: 500px
   :Path: plugin.mail.settings.scss

   Global rounded radius of rounded-corners

global-breakpoint
-----------------

.. confval:: global-breakpoint

   :type: string
   :Default: $global-width + $global-gutter
   :Path: plugin.mail.settings.scss

   Global media query to switch from desktop to mobile styles


Content Objects
===============

This extension brings two new content object :typoscript:`EMOGRIFIER` and :typoscript:`SCSS`

:typoscript:`EMOGRIFIER` is used to transform all given css files to inline styles.

:typoscript:`SCSS` is used to transform given scss files to css.

See `EXT:mail/Configuration/TypoScript/ContentElements/setup.typoscript` for how to use.
