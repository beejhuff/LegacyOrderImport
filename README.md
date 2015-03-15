# LegacyOrderImport
Import legacy orders

The legacy_order_id field will be the legacy order number

The columns b_firstname, b_lastname, b_street etc are billing address fields

The same columns exist for shipping if shipping address is different s_firstname, s_lastname, s_street etc.

If street address has more than one line you can split it in the same column using a carriage return

shipping_same_as_billing will be 0 or 1 depending on if the shipping address is the same as billing

customer_firstname and customer_lastname will the the order customer first,last name as it could be different from billing/shipping name

shipping_description can be anything you like. Will default to 'Standard' if you leave empty

checkout_method should be 'guest' if the customer does not have an account, 'user' if they have an account

created_at will be the order date YYYY-MM-DD

total_items will be the total number of items for the order

grand_total and subtotal are the order grand total and subtotal

total paid is the amount that has been paid

subtotal + shipping amount + tax amount = grand total


There are many other columns I've omitted from the sample that can be added if needed.

The payment method will default to Saved CC, you can use column 'p_method' to specify any valid payment method, checkmo, ccsave, paypal_express etc.
