Installing the module
---------------------

- unzip the .zip file
- copy over the images and includes trees
  - over ftp:
    - put -R includes trees

Activating the module
---------------------
 - Go to the admin backoffice
 - At the top, go to Modules > Payment
 - Select the GetFinancing Payment Module
 - On the right, Click the +Install button
 - Configure the settings that GetFinancing provided you.
 - Update the changes.

Adding additional needed libraries
----------------------------------

Edit your template and add this line between your HEAD tags:
<script type="text/javascript" src="https://partner.getfinancing.com/libs/1.0/getfinancing.js"></script>

NOTE: Normally this template is located at:
zencart_root/includes/templates/[your_template]/common/

Opening the firewall
--------------------
Your server needs to be able to communicate with the GetFinancing platform.
For this to work, the firewall needs to allow outgoing connections on ports
10000 and 10001.

If you do not know how to do this yourself, please ask your hosting provider
to do this for you.

Testing
-------

In the complete integration guide that you can download from our portal,
you can see various test personae that you can use for testing.

Switching to production
-----------------------

 - Go to the admin backoffice
 - At the top, go to Modules > Payment
 - Select the GetFinancing Payment Module and click Edit Button.
 - In the settings, switch to Production.

Note that after this change, you should no longer use the test personae you
used for testing, and all requests go to our production platform.

Module notes
------------
 - when checking out with GetFinancing, the quote only gets converted to
   an order after the loan has been preapproved.  This allows for easy
   rollback to other payment methods in case the loan is not preapproved.
 - You will be able to change the default order status when GetFinancing
   Payment Method used at the Module Settings.

Compatibility
-------------
 - This module has been tested with ZenCart version 1.3.9
