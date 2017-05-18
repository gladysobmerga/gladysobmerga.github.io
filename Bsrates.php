<?php

/**
* Bluesky Shipping Rates
*
* PHP version 5.3
*
* @category Bluesky
* @package Bluesky_ShippingRates
* @author Gladys Obmerga <gladys.obmerga@2ndoffice.co>
* @copyright 2017 Gladys Obmerga 2nd Office Inc
* @link www.2ndoffice.ph
*
*/

class Bluesky_ShippingRates_Model_Bsrates
extends Mage_Shipping_Model_Carrier_Abstract
implements Mage_Shipping_Model_Carrier_Interface
{
  protected $_code = 'bluesky_shippingrates';
  protected $_fc = 'full_crates';
  protected $_fca = 'full_crates_areaa';
  protected $_indiv = 'individual_units';
  protected $_indiva = 'individual_units_areaa';
  protected $_fcindiv = 'fc_indiv';
  protected $_fcindiva = 'fc_indiv_areaa';
  protected $_sample = 'sample';
  protected $_shippingTitleFree = 'shipping_title_free';
  protected $_shippingTitle3to4 = 'shipping_title_3to4';
  protected $_shippingTitleExpress = 'shipping_title_express';
  public $price = 0;
  public $priceexpress = 0;

  public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!Mage::getStoreConfig('carriers/'.$this->_code.'/active')) {
            return false;
        }
         
 
        $handling = Mage::getStoreConfig('carriers/'.$this->_code.'/handling');
        $result = Mage::getModel('shipping/rate_result');

        global $price;
        global $priceexpress;
        $cratesNo = 0;
        $individualNo = 0;
        $sampleNo = 0;
        $priceCrates = 0;
        $priceIndiv = 0; 
        $priceSamp = 0;
        $configPrice = $this->getConfigData('price');
        $configSprice = $this->getConfigData('sprice');
        $configExprice = $this->getConfigData('exprice');
        $prodQty = $request->getPackageQty();
        $country_id = $request->getDestCountryId();
        $country_idcsv = $request->getOrigCountryId();
        $destPostcode = strtoupper($request->getDestPostcode());
        $productType = array ('prodCrates' => 0, 'prodIndividual' => 0, 'prodSample' => 0);
        $bsFile = 'bsrates.csv';
        $bsFileLoc = Mage::getBaseDir('var') . DS . 'export' . DS . $bsFile;
        $csvObject = new Varien_File_Csv();
        $bsTable =  $csvObject->getData($bsFileLoc);
        $groupCrates = array();
        $groupIndiv = array();
        $groupSample = array();

        //store crates, individual and sample product in an array

        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {

              $product_id = $item->getProductId();
              $product = Mage::getModel('catalog/product')->load($product_id);
              $productOption = $product->getAttributeText('shipping_product_type');

              if($productOption){ //Do all products have this attribute?
                switch($productOption){
                   case "Crates":
                       $productType['prodCrates'] += 1;
                       $groupCrates[] = $item;
                       $cratesNo += $item->getQty();
                       break;
                   case "Individual":
                       $productType['prodIndividual'] += 1;
                       $groupIndiv[] = $item;
                       $individualNo += $item->getQty();
                       break;
                    case "Sample":
                       $productType['prodSample'] += 1;
                       $groupSample[] = $item;
                       $sampleNo += $item->getQty();
                       break;
                 }
              } 

              else {
                continue;
              } 
            }
        }

        //check if country id is UK

        if ($country_id == "GBP" || $country_id == "GB") {

          $checkArea = 0;

          //compare if postcode is in the table. If yes, then store the value
          foreach ($bsTable as list($row,$value)) {

            $validator = @preg_match($row,$destPostcode);

            if ($validator == 1) {

                $areaA = $row;
                $areaRate = $value;
                $checkArea++;
            }
          }

          if ($checkArea != 0) {

            //Full Crate only + Table A 
            if ($productType['prodCrates'] !== 0 && $productType['prodIndividual'] == 0) {

              $priceCrates += $areaRate * $cratesNo;
              $priceSamp += $configSprice * $sampleNo; //Full Crate + Sample + Table A

              $price += $priceCrates + $priceSamp;
              $result->append($this->_getShippingDaysRate());
            }

            //Individual Units Only + Table A
            else if ($productType['prodCrates'] == 0 && $productType['prodIndividual'] !== 0) {
              
              $priceIndiv += (1*$areaRate) + (1* $configPrice);
              $priceSamp += $configSprice * $sampleNo; // Individual Units + Sample + Table A

              $price += $priceIndiv + $priceSamp;
              $result->append($this->_getShippingDaysRate());
            }


            //Full Crates + Individual
            else if ($productType['prodCrates'] !== 0 && $productType['prodIndividual'] !== 0) {

              $priceCrates += $areaRate * $cratesNo;
              $priceIndiv += (1*$areaRate) + (1* $configPrice);
              $priceSamp += $configSprice * $sampleNo;

              $price += $priceCrates + $priceIndiv + $priceSamp;
              $result->append($this->_getShippingDaysRate());
            }
            //Sample units only  + Free Area
            else if (($sampleNo !==0 && $cratesNo == 0) && $individualNo == 0){

                $price += $configSprice * $sampleNo;

                $result->append($this->_getShippingDaysRate());
            }
          }

          //if postcode is in free delivery area

          else {

            //Full Crate only  + Free Area
            if($productType['prodCrates'] !== 0 && $productType['prodIndividual'] == 0){
                
                if ($productType['prodSample'] !==0){
                  $priceCrates += 0;
                  $priceSamp += $configSprice * $sampleNo;

                  $price += $priceCrates + $priceSamp;
                  $priceexpress = $cratesNo * $configExprice;
                  $result->append($this->_getShippingDaysRate());
                  $result->append($this->_getShippingExpressRate());
                }

                else {
                  $price = 0;
                  $priceexpress = $cratesNo * $configExprice;
                  $result->append($this->_getShippingFreeRate());
                  $result->append($this->_getShippingExpressRate());
                }
                
            }

            //Sample units only  + Free Area
            else if (($sampleNo !==0 && $cratesNo == 0) && $individualNo == 0){

                $price += $configSprice * $sampleNo;
                $result->append($this->_getShippingDaysRate());
            }

            else {

                $priceIndiv += $configPrice;
                $priceSamp += $configSprice * $sampleNo;

                $price += $priceIndiv + $priceSamp;
                $result->append($this->_getShippingDaysRate());
                

                if($cratesNo !== 0){
                  $priceexpress = ($cratesNo * $configExprice) + $priceIndiv;
                  $result->append($this->_getShippingExpressRate());
                }
            }
            
          }
        }

        else {
          
            $error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('name'));
            $error->setErrorMessage($this->getConfigData('specificerrmsg'));

            return $error;
        }
        
        return $result;
    }

    protected function _getShippingFreeRate(){
        $show = true;
        global $price;
        if($show){ // This if condition is just to demonstrate how to return success and error in shipping methods
 
            $method = Mage::getModel('shipping/rate_result_method');
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod($this->_shippingTitleFree);
            $method->setMethodTitle('Full Crate - Free Shipping');
            $method->setPrice($price);
            $method->setCost($price);
            
            return $method;
 
        } else{
            $error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('name'));
            $error->setErrorMessage($this->getConfigData('specificerrmsg'));

            return $error;
        }
    }

    protected function _getShippingDaysRate(){
        $show = true;
        global $price;
        if($show){ // This if condition is just to demonstrate how to return success and error in shipping methods
 
            $method = Mage::getModel('shipping/rate_result_method');
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod($this->_shippingTitle3to4);
            $method->setMethodTitle('3 to 4 working days');
            $method->setPrice($price);
            $method->setCost($price);
            
            return $method;
 
        } else{
            $error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('name'));
            $error->setErrorMessage($this->getConfigData('specificerrmsg'));

            return $error;
        }
    }

    protected function _getShippingExpressRate(){
        $show = true;
        global $priceexpress;
        if($show){ // This if condition is just to demonstrate how to return success and error in shipping methods
 
            $method = Mage::getModel('shipping/rate_result_method');
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod($this->_shippingTitleExpress);
            $method->setMethodTitle('Next Working Days');
            $method->setPrice($priceexpress);
            $method->setCost($priceexpress);
            
            return $method;
 
        } else{
            $error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('name'));
            $error->setErrorMessage($this->getConfigData('specificerrmsg'));

            return $error;
        }
    }

  
    //  public function getRate(Mage_Shipping_Model_Rate_Request $request) {
    //     return Mage::getResourceModel('shipping/carrier_tablerate')->getRate($request);
    // }

    public function getAllowedMethods() {
        return array(
          'shipping_title_free'=>'Full Crate - Free Shipping',
          'shipping_title_3to4'=>'3 to 4 working days',
          'shipping_title_express'=>'Next Working Days',
        );
    }
}