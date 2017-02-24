<!doctype html>
<html lang="en">
<head>
<meta name="renderer" content="webkit" />
<meta charset="UTF-8">
<title>Add xxxx</title>
<link rel="stylesheet" type="text/css" href="/static/css/phlconwings.css" />
</head>
<body>
<table width="100%" cellspacing="0" cellpadding="0" border="0">
  <tr>
    <td>
      <div class="path">
        <a href="{{url('index/index')}}">Home</a> <span class="split">/</span>
        <a href="{{url('##ctl##/index')}}">xxxx List</a> <span class="split">/</span>
        <a>Add xxxx</a>
      </div>
    </td>
  </tr>
</table>
<form action="{{url('##ctl##/add')}}" onsubmit="return false;">
    <table width="100%" cellspacing="0" cellpadding="8" border="0" class="formtable">
      <tr class="th">
        <td colspan="2">Add xxxx</td>
      </tr>
      ##addblock##
      <tr class="tb2">
        <td>&nbsp;</td>
        <td>
          <a role="pw_submit" class="sbtn blue" msg="Saved successfully!" redirect="{{url('##ctl##/index')}}"> Save </a>
        </td>
      </tr>
    </table>
</form>
<script type="text/javascript" src="/static/js/jquery-1.8.3.min.js"></script>
<script type="text/javascript" src="/static/js/artDialog/jquery.artDialog.js?skin=default"></script>
<script type="text/javascript" src="/static/js/PhalconWings.js"></script>
</body>
</html>