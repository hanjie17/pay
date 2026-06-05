<?php
$is_defend = true;
$nosession = true;
require './includes/common.php';

@header('Content-Type: text/html; charset=UTF-8');

$other=isset($_GET['other'])?true:false;
$trade_no=daddslashes($_GET['trade_no']);
$sitename=base64_decode(daddslashes($_GET['sitename']));
$row=$DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' limit 1");
if(!$row)sysmsg('该订单号不存在，请返回来源地重新发起请求！');
if($row['status']==1)sysmsg('该订单已完成支付，请勿重复支付');
$gid = $DB->getColumn("SELECT gid FROM pre_user WHERE uid='{$row['uid']}' limit 1");
$paytype = \lib\Channel::getTypes($gid);

if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
	$paytype = array_values($paytype);
	foreach($paytype as $i=>$s){
		if($s['name']=='wxpay'){
			$temp = $paytype[$i];
			$paytype[$i] = $paytype[0];
			$paytype[0] = $temp;
		}
	}
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>收银台 | <?php echo $sitename ? $sitename : $conf['sitename']?></title>
<link rel="icon" href="/favicon.ico">
<link href="/assets/css/cashier.css" rel="stylesheet">
</head>
<body class="cashier-body">

<!-- Header -->
<header class="cashier-header">
  <div class="cashier-header__inner">
    <div class="cashier-header__brand">
      <img class="cashier-header__logo" src="/assets/img/logo.png" alt="Logo">
      <span class="cashier-header__title">收银台</span>
    </div>
    <span class="cashier-header__badge">安全支付</span>
  </div>
</header>

<main class="cashier-main">
  <input type="hidden" name="trade_no" value="<?php echo $trade_no?>">

  <?php if($other){ ?>
  <!-- Alert -->
  <div class="cashier-alert">
    <div class="cashier-alert__content">
      <div class="cashier-alert__title">当前支付方式暂时关闭维护，请更换其他方式支付</div>
      <div class="cashier-alert__desc">如果您需要微信支付，请将微信余额转到QQ再选择QQ钱包支付！</div>
      <a class="cashier-alert__link" href="./wx.html">查看转账教程 &gt;</a>
    </div>
  </div>
  <?php } else { ?>
  <!-- Order Info -->
  <div class="cashier-section">
    <div class="cashier-section__header">
      <span class="cashier-section__title">订单信息</span>
    </div>
    <div class="cashier-section__body">
      <ul class="order-info__list">
        <li class="order-info__item">
          <span class="order-info__label">商品名称</span>
          <span class="order-info__value"><?php echo $row['name']?></span>
        </li>
        <li class="order-info__item">
          <span class="order-info__label">订单编号</span>
          <span class="order-info__value"><?php echo $trade_no?></span>
        </li>
        <li class="order-info__item">
          <span class="order-info__label">创建时间</span>
          <span class="order-info__value"><?php echo $row['addtime']?></span>
        </li>
      </ul>
      <div class="order-amount">
        <span class="order-amount__label">应付金额：</span>
        <span class="order-amount__symbol">&yen;</span>
        <span class="order-amount__value"><?php echo $row['money']?></span>
      </div>
      <div class="order-fee-notice">
        <span class="order-fee-notice__icon">!</span>
        实际支付时官方将收取 <strong>1%</strong> 的手续费
      </div>
    </div>
  </div>
  <?php } ?>

  <!-- Payment Methods -->
  <div class="cashier-section">
    <div class="cashier-section__header">
      <span class="cashier-section__title">选择支付方式</span>
    </div>
    <div class="cashier-section__body">
      <ul class="pay-list types">
        <?php foreach($paytype as $rows){ ?>
        <li class="pay-list__item pay_li" data-value="<?php echo $rows['id']?>">
          <img class="pay-list__icon" src="/assets/icon/<?php echo $rows['name']?>.ico" alt="<?php echo $rows['showname']?>">
          <div class="pay-list__info">
            <div class="pay-list__name"><?php echo $rows['showname']?></div>
          </div>
          <span class="pay-list__radio"></span>
        </li>
        <?php } ?>
      </ul>
    </div>
  </div>

  <!-- Pay Action -->
  <div class="pay-action">
    <div class="pay-action__info">
      <div>需支付 <span class="pay-action__price">&yen;<?php echo $row['realmoney'] ? $row['realmoney'] : $row['money']?></span></div>
      <?php if($row['realmoney'] && $row['realmoney'] != $row['money']){ ?>
      <div class="pay-action__fee">含 <?php echo $row['realmoney'] - $row['money']?> 元手续费</div>
      <?php } ?>
    </div>
    <a class="pay-action__btn immediate_pay">确认支付</a>
  </div>

  <!-- Footer -->
  <footer class="cashier-footer">
    <div class="cashier-footer__trust">
      <span>安全认证</span>
      <span>加密传输</span>
      <span>隐私保护</span>
    </div>
    <?php echo $sitename ? $sitename : $conf['sitename']?>
  </footer>
</main>

<!-- Modal -->
<div class="cashier-modal mt_agree">
  <div class="cashier-modal__content mt_agree_main">
    <h3 class="cashier-modal__title">提示</h3>
    <p class="cashier-modal__message" id="errorContent"></p>
    <a class="cashier-modal__btn close_btn">知道了</a>
  </div>
</div>

<script src="<?php echo $cdnpublic?>jquery/1.12.4/jquery.min.js"></script>
<script>
$(function(){
  // Payment method selection
  $(".types .pay_li").on("click", function(){
    $(".types .pay_li").removeClass("active");
    $(this).addClass("active");
  });

  // Submit payment
  $(document).on("click", ".immediate_pay", function(){
    var active = $(".types").find(".active");
    var value = active.length ? active.attr("data-value") : "";
    var trade_no = $("input[name='trade_no']").val();
    if(!value){
      $("#errorContent").text("请选择支付方式");
      $(".mt_agree").addClass("show");
      return;
    }
    window.location.href = "./submit2.php?typeid=" + value + "&trade_no=" + trade_no;
  });

  // Close modal
  $(".close_btn").on("click", function(){
    $(".mt_agree").removeClass("show");
  });

  // Select first payment method by default
  $(".types .pay_li:first").click();
});
</script>
</body>
</html>
