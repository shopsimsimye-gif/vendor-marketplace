// vendor-edit-product.js — commission rate is provided via window.vmp_edit_product_data (localized)
window.vmp_commission_rate = (typeof window.vmp_edit_product_data !== 'undefined' && window.vmp_edit_product_data.commissionRate) 
    ? window.vmp_edit_product_data.commissionRate 
    : null;
