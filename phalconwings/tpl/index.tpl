<!doctype html>
<html lang="en">
<head>
<meta name="renderer" content="webkit" />
<meta charset="UTF-8">
<title>##mname## List</title>
<link rel="stylesheet" type="text/css" href="/static/css/phalconwings.css" />
</head>
<body>
<table width="100%" cellspacing="0" cellpadding="0" border="0">
  <tr>
    <td>
      <div class="path">
        <a href="{{url('index/index')}}">Home</a> <span class="split">/</span>
        <a href="{{url('##ctl##/index')}}">##mname## List</a>
      </div>
    </td>
  </tr>
</table>
<table width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td><a class="sbtn blue" href="{{url('##ctl##/add')}}">New ##mname##</a></td>
        <td>
        <!--
        <form method="get" action="{{url('##ctl##/index')}}">
            <input type="submit" value=" Search " class="sbtn blue" />
        </form>
        -->
        </td>
    </tr>
 </table>
 <table width="100%" cellspacing="0" cellpadding="8" border="0" class="datatable">
    <thead>
      <tr class="th">
        <td width="30">#</td>
        ##thblock##
        <td class="ops">Operation</td>
      </tr>
    </thead>
    <tbody>
      {% for ##ctl## in pager.items %}
      <tr class="tb">
        <td><input type="checkbox" class="ids" name="ids[]" value="{{##ctl##.##pk##}}" /></td>
        ##tdblock##
        <td class="ops">
          <a class="edit" href="{{url('##ctl##/edit')}}?id={{##ctl##.##pk##}}" title="Modify">&nbsp;</a>
          <a class="del" role="pw_confirm" msg="Are you sure to delete this item?" url="{{url('##ctl##/del')}}?id={{##ctl##.##pk##}}" title="Delete">&nbsp;</a>
        </td>
      </tr>
      {% else %}
      <tr class="tb">
          <td colspan="##colspan##">No record</td>
      </tr>
      {% endfor %}
    </tbody>
  </table>
  <table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:8px;">
  <tr class="tb2">
      <td><a class="sbtn blue" role="checkall">Check All</a></td>
      <td align="right">
        <div class="pagenation">
          {{ link_to("##ctl##/index", "First") }}
          {{ link_to("##ctl##/index?page=" ~ pager.before, "Prev") }}
          {{ link_to("##ctl##/index?page=" ~ pager.next, "Next") }}
          {{ link_to("##ctl##/index?page=" ~ pager.last, "Last") }}
          <span class="help-inline">{{ pager.current }} of {{ pager.total_pages }}</span>
        </div>
      </td>
  </tr>
  </table>
<script type="text/javascript" src="/static/js/jquery-1.8.3.min.js"></script>
<script type="text/javascript" src="/static/js/artDialog/jquery.artDialog.js?skin=default"></script>
<script type="text/javascript" src="/static/js/PhalconWings.js"></script>
</body>
</html>