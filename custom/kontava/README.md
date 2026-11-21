# [KONTAVA](https://kontava.fr) FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Features

This module was initially designed for the clients of solution [Kontava](https://kontava.fr), but we believe it can be usefull for everyone !

It includes:
 - A beautifull theme (that you need to activate),
 - A new 'homepage' fitted with a dedicated left menu with main shortcuts,
 - Many usefull widgets and graphics,
 - A feature on widgets to collapse or expend the Kontava widgets,
 - Simplified templates for new product/service/customer management.

####On top of this free module, Kontava proposes a complete secured & online solution, cost effective with free trial. Please visit [kontava.fr](https://kontava.fr).


<!-- ![Homepage Kontava](img/module_01.png?raw=true "Homepage Kontava"){imgmd} -->
###Kontava homepage
![Kontava Homepage](img/module_01.png?raw=true "Kontava Homepage")
###Kontava theme setting
![Kontava Theme](img/module_02.png?raw=true "Kontava Theme")
###Kontava simplified interface example 
![Kontava Simplifed templates](img/module_03.png?raw=true "Kontava Simplifed templates")
###Kontava widget collapsing/expending 
![Kontava widget collapsing/expending](img/module_05.png?raw=true "Kontava widget collapsing/expending")




<!-- Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Translations

Translations can be completed manually by editing files into directories *langs*. -->

<!--
This module contains also a sample configuration for Transifex, under the hidden directory [.tx](.tx), so it is possible to manage translation using this service.

For more informations, see the [translator's documentation](https://wiki.dolibarr.org/index.php/Translator_documentation).

There is a [Transifex project](https://transifex.com/projects/p/dolibarr-module-template) for this module.
-->

<!--

## Installation

### From the ZIP file and GUI interface

If the module is a ready to deploy zip file, so with a name module_xxx-version.zip (like when downloading it from a market place like [Dolistore](https://www.dolistore.com)),
go into menu ```Home - Setup - Modules - Deploy external module``` and upload the zip file.

Note: If this screen tell you that there is no "custom" directory, check that your setup is correct:

- In your Dolibarr installation directory, edit the ```htdocs/conf/conf.php``` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading ```//```) and assign a sensible value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```

### From a GIT repository

Clone the repository in ```$dolibarr_main_document_root_alt/kontava```

```sh
cd ....../custom
git clone git@github.com:gitlogin/kontava.git kontava
```

### <a name="final_steps"></a>Final steps

From your browser:

  - Log into Dolibarr as a super-administrator
  - Go to "Setup" -> "Modules"
  - You should now be able to find and enable the module

-->

## Licenses

### Main code

GPLv3 or any later version. See file COPYING for more information.

### Documentation

All texts and readmes are licensed under GFDL.
