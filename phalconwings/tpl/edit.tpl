<!doctype html>
<html lang="en">
<head>
<meta name="renderer" content="webkit" />
<meta charset="UTF-8">
<title>Modify xxxx</title>
<link rel="stylesheet" type="text/css" href="/static/css/phlconwings.css" />
</head>
<body>
<table width="100%" cellspacing="0" cellpadding="0" border="0">
  <tr>
    <td>
      <div class="path">
        <a href="{{url('index/index')}}">Home</a> <span class="split">/</span>
        <a href="{{url('##ctl##/index')}}">xxxx</a> <span class="split">/</span>
        <a>Modify xxxx</a>
      </div>
    </td>
  </tr>
</table>
<form action="{{url('##ctl##/edit')}}" onsubmit="return false;">
<input type="hidden" name="##pk##" value="{{'.$var.'.'.$pk.'}}" />
  <table width="100%" cellspacing="0" cellpadding="8" border="0" class="formtable">
    <tr class="th">
      <td colspan="2">Modify xxxx</td>
    </tr>
    ##editblock##
    <tr class="tb2">
      <td>&nbsp;</td>
      <td>
        <a role="pw_submit" class="sbtn blue" msg="Modifyed successfully!" redirect="{{url('##ctl##/index')}}"> Save change </a>
      </td>
    </tr>
  </table>
</form>
<script type="text/javascript" src="/static/js/jquery-1.8.3.min.js"></script>
<script type="text/javascript" src="/static/js/artDialog/jquery.artDialog.js?skin=default"></script>
<script type="text/javascript" src="/static/js/PhalconWings.js"></script>
</body>
</html>