<?PHP

/*
 * Copyright (C) 2013       Francis Appels <francis.appels@z-application.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 *  \file       htdocs/extdirect/class/ExtDirectProduct.class.php
 *  \brief      Sencha Ext.Direct products remoting class
 */

require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

/** ExtDirectProduct class
 * 
 * Class to access products with CRUD(L) methods to connect to Extjs or sencha touch using Ext.direct connector
 */
class ExtDirectCategorie extends Categorie
{
    
    private $_user;
    
    /** Constructor
     *
     * @param string $login user name
     */
    public function __construct($login) 
    {
        global $langs,$db,$user;
        
        if (!empty($login)) {
            if ($user->fetch('', $login)>0) {
                $user->getrights();
                $this->_user = $user;  //product.class uses global user
                if (isset($this->_user->conf->MAIN_LANG_DEFAULT) && ($this->_user->conf->MAIN_LANG_DEFAULT != 'auto')) {
                    $langs->setDefaultLang($this->_user->conf->MAIN_LANG_DEFAULT);
                }
                $langs->load("categories");
                $this->db = $db;
            }
        }
    }
    
    
    /**
     *    Load products from database into memory
     *
     *    @param    stdClass    $param  filter with elements:
     *      id                  Id of product to load
     *      ref                 Reference of product, name
     *      warehouse_id        filter product on a warehouse
     *      multiprices_index   filter product on a multiprice index
     *      barcode             barcode of product 
     *    @return     stdClass result data or -1
     */
    public function readCategorie(stdClass $param)
    {
        global $conf,$langs;

        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->categorie->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;
        $id = 0;
        $label = '';

        if (isset($param->filter)) {
            foreach ($param->filter as $key => $filter) {
                if ($filter->property == 'id') $id=$filter->value;
                else if ($filter->property == 'label') $label=$filter->value;
            }
        }
        
        if (($id > 0) || ($label != '')) {
            if (($result = $this->fetch($id, $label)) < 0)    return $result;
            if (!$this->error) {
                $row->id           = $this->id ;
                $row->fk_parent    = $this->fk_parent;
                $row->label        = $this->label;
                $row->description  = $this->description?$this->description:'';
                $row->company_id   = $this->socid; 
                // 0=Product, 1=Supplier, 2=Customer/Prospect, 3=Member
                $row->type= $this->type;
                $row->entity= $this->entity;
                array_push($results, $row);
            } else {
                return 0;
            }
        }
        
        return $results;
    }


    /**
     * Ext.direct method to Create product
     * 
     * @param unknown_type $params object or object array with product model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    public function createCategorie($params) 
    {

        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->categorie->creer)) return PERMISSIONERROR;
        $notrigger=0;
        $paramArray = ExtDirect::toArray($params);
        
        foreach ($paramArray as &$param) {
            // prepare fields
            $this->prepareFields($param);
            if (($result = $this->create($this->_user)) < 0) return $result;
            
           
            $param->id=$this->id;
        }

        if (is_array($params)) {
            return $paramArray;
        } else {
            return $param;
        }
    }

    /**
     * Ext.direct method to update product
     * 
     * @param unknown_type $params object or object array with product model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    public function updateCategorie($params) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->categorie->creer)) return PERMISSIONERROR;
        // dolibarr update settings

        $paramArray = ExtDirect::toArray($params);
        foreach ($paramArray as &$param) {
            // prepare fields
            if ($param->id) {
                $id = $param->id;
                $this->id = $id;
                if (($result = $this->fetch($id, '')) < 0)    return $result;
                $this->prepareFields($param);
                // update
                if (($result = $this->update($this->_user)) < 0)   return $result;
            } else {
                return PARAMETERERROR;
            }
        }
        if (is_array($params)) {
            return $paramArray;
        } else {
            return $param;
        }
    }

    /**
     * Ext.direct method to destroy product
     * 
     * @param unknown_type $params object or object array with product model(s)
     * @return Ambigous <multitype:, unknown_type>|unknown
     */
    public function destroyCategorie($params) 
    {
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->categorie->supprimer)) return PERMISSIONERROR;
        $paramArray = ExtDirect::toArray($params);

        foreach ($paramArray as &$param) {
            // prepare fields
            if ($param->id) {
                $id = $param->id;
                $this->id = $id;
                // delete product
                if (($result = $this->delete($this->_user)) <= 0)    return $result;
            } else {
                return PARAMETERERROR;
            }
        }

        if (is_array($params)) {
            return $paramArray;
        } else {
            return $param;
        }
    }
    
    /**
     * public method to read a list of products
     *
     * @param stdClass $param to filter on order status
     * @return     stdClass result data or -1
     */
    public function readCategorieList(stdClass $param) 
    {
        global $conf;
        if (!isset($this->db)) return CONNECTERROR;
        if (!isset($this->_user->rights->produit->lire)) return PERMISSIONERROR;
        $results = array();
        $row = new stdClass;
        $type = 0;
        
        if (isset($param->limit)) {
            $limit = $param->limit;
            $start = $param->start;
        }
        if (isset($param->filter)) {
            foreach ($param->filter as $key => $filter) {
                if ($filter->property == 'type') $type=$filter->value;
            }
        }       
        
        if (($cats = $this->get_all_categories($type, false)) < 0) return $cats;

        foreach ($cats as $cat) {
            $row=null;
            $row->id = $cat->id;
            $row->categorie = $cat->label;
            array_push($results, $row); 
        }
        return $results;
    }
        
    /**
     * private method to copy fields into dolibarr object
     * 
     * @param stdclass $param object with fields
     * @return null
     */
    private function prepareFields($param) 
    {
        isset($param->id) ? ( $this->id = $param->id ) : null;
        isset($param->label) ? ( $this->label = $param->label) : null;
        isset($param->fk_parent) ? ( $this->fk_parent =$param->fk_parent) : null;
        isset($param->description) ? ( $this->description = $param->description) : null;
        // 0=Product, 1=Supplier, 2=Customer/Prospect, 3=Member
        isset($param->type) ? ( $this->type = $param->type) : null;
        isset($param->company_id) ? ( $this->socid = $param->company_id) : null;
        isset($param->entity) ? ( $this->entity = $param->entity ) : null;
    } 
}