General Instructions
-----------------------------
1. Create your merchant account to offer monthly payment options to your consumers directly on your ecommerce from here (http://www.getfinancing.com/signup) if you haven't done it yet.
2. Download our module from the latest release here https://github.com/GetFinancing/getfinancing-zencart/releases or all the code in a zip file from here https://github.com/GetFinancing/getfinancing-zencart/archive/master.zip
3. Setup the module with the information found under the Integration section on your portal account https://partner.getfinancing.com/partner/portal/. Also remember to change the postback url on your account for both testing and production environments. 
4. Once the module is working properly and the lightbox opens on the request, we suggest you to add some conversion tools to your store so your users know before the payment page that they can pay monthly for the purchases at your site. 
5. Check our documentation (www.getfinancing.com/docs) or send us an email at integrations@getfinancing.com if you have any doubt or suggestions for this module. You can also send pull requests to our GitHub account (http://www.github.com/GetFinancing) if you feel you made an improvement to this module.

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

```javascript
<script type="text/javascript" src="https://cdn.getfinancing.com/libs/1.0/getfinancing.js"></script>
```

NOTE: Normally this template is located at:
zencart_root/includes/templates/[your_template]/common/

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
