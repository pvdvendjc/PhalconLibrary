**Usage of this library**

First setup your own baseController which extends the Djc\Phalcon\Controllers\BaseController
Override the following functions
- loginAdmin
- checkAccess
- afterInitialize (this can be left empty)

For each default action there is a before and after function. So storeAction calls 
- beforeStoreAction at the start of this function
- afterStoreAction at the end of this function

See the function docs for use of the correct var's. Return booleans at the end of the function.

For the installation of the database (with the morph functions of Phalcon) is a check at the init of the BaseController.
The installation uses functions in this library to Install and update the tables. The up() and down() functions in the morph will also be used.

After this setup your own BaseModel which extends the Djc\Phalcon\Models\BaseModel
Check the var's you want to override globally.

If you want to use the AclService from the Library you have to setup this in you BaseController
Fill the var _aclService with a construct of the Djc\Phalcon\Services\AclService with the correct classes for the AclItem and Acl

**Available for**   
Phalcon 3.4 -> branch phalcon3  
Phalcon 4.0 -> branch phalcon4
