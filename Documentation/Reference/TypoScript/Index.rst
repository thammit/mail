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

.. confval:: templatesRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Templates/Mail/
   :Path: plugin.mail.view.page.templatesRootPath

   Path to template root for mail pages (FE)

.. _ts-page-view-partials-root-path:

.. confval:: partialsRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Partials/
   :Path: plugin.mail.view.page.partialsRootPath

   Path to partials root for mail pages (FE)

.. _ts-page-view-layouts-root-path:

.. confval:: layoutsRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Layouts/
   :Path: plugin.mail.view.page.layoutsRootPath

   Path to layouts root for mail pages (FE)

Content View Settings
=====================

All content view settings need to be prefixed with  :typoscript:`plugin.mail.view.content`.


.. only:: html

   .. contents:: Properties
      :depth: 1
      :local:

.. _ts-content-view-templates-root-path:

.. confval:: templatesRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Templates/ContentElements/
   :Path: plugin.mail.view.content.templatesRootPath

   Path to template root for mail contents (FE)

.. _ts-content-view-partials-root-path:

.. confval:: partialsRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Partials/
   :Path: plugin.mail.view.content.partialsRootPath

   Path to partials root for mail contents (FE)

.. _ts-content-view-layouts-root-path:

.. confval:: layoutsRootPath

   :type: string
   :Default: EXT:mail/Resources/Private/Layouts/
   :Path: plugin.mail.view.content.layoutsRootPath

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

.. confval:: title

   :type: string
   :Default: Newsletter
   :Path: plugin.mail.settings.header.title

   Title of the mail header

.. _ts-content-header-image-src:

.. confval:: image.src

   :type: string
   :Default: EXT:mail/Resources/Public/Images/Logo.png
   :Path: plugin.mail.settings.image.src

   Logo source

.. _ts-content-header-image-alt:

.. confval:: image.alt

   :type: string
   :Default: Logo
   :Path: plugin.mail.settings.image.alt

   Logo alt text

.. _ts-content-header-image-width:

.. confval:: image.width

   :type: string
   :Default: 300
   :Path: plugin.mail.settings.image.width

   Logo width

.. _ts-content-header-image-height:

.. confval:: image.height

   :type: string
   :Default:
   :Path: plugin.mail.settings.image.height

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

.. confval:: modifications

   :type: string
   :Default: EXT:mail/Resources/Public/Scss/mail.scss
   :Path: plugin.mail.settings.scss.modifications

   Scss file to modify the default scss of foundation mail

.. _ts-content-scss-primary-color:

.. confval:: primary-color

   :type: color
   :Default: #037eab
   :Path: plugin.mail.settings.scss.primary-color

   Primary color, used with components that support the `.primary` class

.. confval:: secondary-color

   :type: color
   :Default: #777777
   :Path: plugin.mail.settings.scss.secondary-color

   Secondary color, used with components that support the `.secondary` class

.. confval:: success-color

   :type: color
   :Default: #3adb76
   :Path: plugin.mail.settings.scss.success-color

   Color to indicate a positive status or action, used with the `.success` class

.. confval:: warning-color

   :type: color
   :Default: #ffae00
   :Path: plugin.mail.settings.scss.warning-color

   Color to indicate a caution status or action, used with the `.warning` class

.. confval:: alert-color

   :type: color
   :Default: #ec5840
   :Path: plugin.mail.settings.scss.alert-color

   Color to indicate a negative status or action, used with the `.alert` class

.. confval:: light-gray

   :type: color
   :Default: #f3f3f3
   :Path: plugin.mail.settings.scss.light-gray

   Color used for light gray UI items within Foundation

.. confval:: medium-gray

   :type: color
   :Default: #cacaca
   :Path: plugin.mail.settings.scss.medium-gray

   Color used for medium gray UI items within Foundation

.. confval:: dark-gray

   :type: color
   :Default: #8a8a8a
   :Path: plugin.mail.settings.scss.dark-gray

   Color used for dark gray UI items within Foundation

.. confval:: black

   :type: color
   :Default: #0a0a0a
   :Path: plugin.mail.settings.scss.black

   Color used for black ui items within Foundation

.. confval:: white

   :type: color
   :Default: #fefefe
   :Path: plugin.mail.settings.scss.white

   Color used for white ui items within Foundation

.. confval:: pre-color

   :type: color
   :Default: #ff6908
   :Path: plugin.mail.settings.scss.pre-color

   Code color (<pre>)

.. confval:: header-color

   :type: color
   :Default: #444444
   :Path: plugin.mail.settings.scss.header-color

   Headlines color

.. confval:: global-font-color

   :type: color
   :Default: #444444
   :Path: plugin.mail.settings.scss.global-font-color

   Text color

.. confval:: header-background-color

   :type: color
   :Default: #037eab
   :Path: plugin.mail.settings.scss.header-background-color

   Header background color

.. confval:: body-background

   :type: string
   :Default: $light-gray
   :Path: plugin.mail.settings.scss.body-background

   Body background color

.. confval:: container-background-color

   :type: string
   :Default: $white
   :Path: plugin.mail.settings.scss.container-background-color

   Container background color

.. confval:: footer-background-color

   :type: string
   :Default: $light-gray
   :Path: plugin.mail.settings.scss.footer-background-color

   Footer background color

.. confval:: global-width

   :type: string
   :Default: 600px
   :Path: plugin.mail.settings.scss.global-width

   Container width

.. confval:: global-width-small

   :type: string
   :Default: 95%
   :Path: plugin.mail.settings.scss.global-width-small

   Container width (small screens)

.. confval:: global-gutter

   :type: string
   :Default: 20px
   :Path: plugin.mail.settings.scss.global-gutter

   Gutter for grid elements

.. confval:: global-gutter-small

   :type: string
   :Default: $global-gutter
   :Path: plugin.mail.settings.scss.global-gutter-small

   Gutter for grid elements (small screens)

.. confval:: global-padding

   :type: string
   :Default: 20px
   :Path: plugin.mail.settings.scss.global-padding

   Global padding

.. confval:: global-margin

   :type: string
   :Default: 16px
   :Path: plugin.mail.settings.scss.global-margin

   Global margin

.. confval:: global-radius

   :type: string
   :Default: 3px
   :Path: plugin.mail.settings.scss.global-radius

   Global radius

.. confval:: global-rounded

   :type: string
   :Default: 500px
   :Path: plugin.mail.settings.scss.global-rounded

   Global rounded radius of rounded-corners

.. confval:: global-breakpoint

   :type: string
   :Default: $global-width + $global-gutter
   :Path: plugin.mail.settings.scss.global-breakpoint

   Global media query to switch from desktop to mobile styles


Content Objects
===============

This extension brings two new content object :typoscript:`EMOGRIFIER` and :typoscript:`SCSS`

:typoscript:`EMOGRIFIER` is used to transform all given css files to inline styles.

:typoscript:`SCSS` is used to transform given scss files to css.

See `EXT:mail/Configuration/TypoScript/ContentElements/setup.typoscript` for how to use.
