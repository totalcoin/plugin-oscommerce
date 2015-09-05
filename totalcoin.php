<?php
require('includes/application_top.php');
require('includes/template_top.php');
require('includes/modules/payment/totalcoin.php');

if ((isset($_REQUEST['url'])) && ($_REQUEST['url'] != '')) {
  $url = urldecode($_REQUEST['url']);
  switch (MODULE_PAYMENT_TOTALCOIN_TYPE_CHECKOUT) {
    case "redirect":
      $cart->reset(true);
      ?>
      <script>
        window.location.replace("<?php echo $url; ?>");
      </script>
      <?php
    break;
    case "iframe":
      ?>
      <iframe src="<?php echo $url; ?>" name="TC-Checkout" width="953" height="600" frameborder="0" style="overflow:hidden"></iframe>
      <?php
    break;
    default:
      $content = '<TOTALCOIN>';
    break;
  }
} else {
  echo 'Error Interno.';
}

require('includes/template_bottom.php');
require('includes/application_bottom.php');

if(!isset($_REQUEST['url']) && isset($_REQUEST['merchant']) && isset($_REQUEST['reference']))
{
  $mb = new totalcoin();
  $mb->tc_ipn_callback($_REQUEST['reference'], $_REQUEST['merchant']);
}

?>
