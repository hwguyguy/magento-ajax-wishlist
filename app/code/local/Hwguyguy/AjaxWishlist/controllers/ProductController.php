<?php
class Hwguyguy_AjaxWishlist_ProductController extends Mage_Core_Controller_Front_Action {
	/**
	 * Add items to wishlist and return response in json.
	 */
	public function addAction() {
		$response = array('status' => 1);

		if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
			Mage::getSingleton('customer/session')->setAfterAuthUrl($this->_getRefererUrl());
			$response['redirect'] = Mage::getUrl('customer/account/login', array(
				'referer' => Mage::helper('core')->urlEncode($this->_getRefererUrl()),
			));
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
		}

		if (!$this->_validateFormKey()) {
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
			return;
		}

		$wishlist = $this->_getWishlist();
		if (!$wishlist) {
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
			return;
		}

		$session = Mage::getSingleton('customer/session');

		$productId = (int)$this->getRequest()->getParam('product');
		if (!$productId) {
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
			return;
		}

		$product = Mage::getModel('catalog/product')->load($productId);
		if (!$product->getId() || !$product->isVisibleInCatalog()) {
			$reponse['message'] = $this->__('Cannot specify product.');
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
			return;
		}

		try {
			$requestParams = $this->getRequest()->getParams();
			if ($session->getBeforeWishlistRequest()) {
				$requestParams = $session->getBeforeWishlistRequest();
				$session->unsBeforeWishlistRequest();
			}
			$buyRequest = new Varien_Object($requestParams);

			$result = $wishlist->addNewItem($product, $buyRequest);
			if (is_string($result)) {
				Mage::throwException($result);
			}
			$wishlist->save();

			Mage::dispatchEvent(
				'wishlist_add_product',
				array(
					'wishlist' => $wishlist,
					'product' => $product,
					'item' => $result
				)
			);

			$referer = $session->getBeforeWishlistUrl();
			if ($referer) {
				$session->setBeforeWishlistUrl(null);
			} else {
				$referer = $this->_getRefererUrl();
			}

			/**
			 *  Set referer to avoid referring to the compare popup window
			 */
			$session->setAddActionReferer($referer);

			Mage::helper('wishlist')->calculate();

			$message = $this->__('%1$s has been added to your wishlist. Click <a href="%2$s">here</a> to continue shopping.',
				$product->getName(), Mage::helper('core')->escapeUrl($referer));

			$response['status'] = 0;
			$response['message'] = $message;
		} catch (Mage_Core_Exception $e) {
			$response['message'] = $this->__('An error occurred while adding item to wishlist: %s', $e->getMessage());
		}
		catch (Exception $e) {
			$response['message'] = $this->__('An error occurred while adding item to wishlist.');
		}

		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
	}

	/**
	 * Retrieve wishlist object
	 * @see Mage_Wishlist_IndexController
	 *
	 * @param int $wishlistId
	 * @return Mage_Wishlist_Model_Wishlist|bool
	 */
	protected function _getWishlist($wishlistId = null) {
		$wishlist = Mage::registry('wishlist');
		if ($wishlist) {
			return $wishlist;
		}

		try {
			if (!$wishlistId) {
				$wishlistId = $this->getRequest()->getParam('wishlist_id');
			}
			$customerId = Mage::getSingleton('customer/session')->getCustomerId();
			/* @var Mage_Wishlist_Model_Wishlist $wishlist */
			$wishlist = Mage::getModel('wishlist/wishlist');
			if ($wishlistId) {
				$wishlist->load($wishlistId);
			} else {
				$wishlist->loadByCustomer($customerId, true);
			}

			if (!$wishlist->getId() || $wishlist->getCustomerId() != $customerId) {
				$wishlist = null;
				Mage::throwException(
					Mage::helper('wishlist')->__("Requested wishlist doesn't exist")
				);
			}

			Mage::register('wishlist', $wishlist);
		} catch (Mage_Core_Exception $e) {
			Mage::getSingleton('wishlist/session')->addError($e->getMessage());
			return false;
		} catch (Exception $e) {
			Mage::getSingleton('wishlist/session')->addException($e,
				Mage::helper('wishlist')->__('Wishlist could not be created.')
			);
			return false;
		}

		return $wishlist;
	}
}
