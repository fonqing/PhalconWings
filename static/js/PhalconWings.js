;(function($){
    if(typeof($.fn.artDialog)=='undefined'){
        alert('PhalconWings require "artDialog(4.1.7) & artDialog iframeTools" Project!');
        return false;
    }
    var PhalconWings = {
        'version'   : '1.0.0',
        'author'    : 'Eric Won <wyl@sindinfo.com>',
		'copyright' : 'SindSmart co.,Ltd 2017'
    };
    PhalconWings.previewImage = function(url , id){
        $('#'+id+'_pic').attr('src',url);
        $('#'+id).val(url);
    };
    PhalconWings.showMessage = function(msg, url){
        art.dialog.tips(msg);
        setTimeout(function(){
            if( typeof(url) == 'undefined' ){
                window.location.reload();
            }else{
                window.location.replace(url);
            }
        }, 1600);
    };
    PhalconWings.doPost = function(o, msg, link){
        var form = $(o).closest('form');
        var url  = form.attr('action');
        $.post(url, form.serialize(), function(response){
            ( response.status == 1 ) ?
                PhalconWings.showMessage(msg, link) :
                art.dialog.alert(response.msg);
        }, 'json');
    };
    PhalconWings.renderDataGrid = function()
    {
        $('.datatable .tb:odd').addClass('odd');
        $('.datatable .tb').hover(function(){
            $(this).addClass('on');
        },function(){
            $(this).removeClass('on');
        });
    };
    PhalconWings.fetchActions = function()
    {
        $('a[role="pw_checkall"]').click(function(){
            $('input.ids').each(function(i, item){
                item.checked = ! item.checked;
            });
        });
        $('a[role="pw_confirm"]').each(function(i, element){
            $(element).click(function(){
                var url = $(this).attr('url'),
                    msg = $(this).attr('msg');
                art.dialog.confirm(msg, function(){
                    $.getJSON(url, function(rs){
                        if( rs.status == 1 ){
                            window.location.reload();
                        }else{
                            art.dialog.alert(rs.msg);
                        }
                    });
                });
            });
        });
        $('a[role="pw_submit"]').each(function(i, element){
            $(element).click(function(){
                var message  = $(this).attr('msg'),
                    redirect = $(this).attr('redirect');
                PhalconWings.doPost(this, message, redirect);
            });
        });
    };
    window.PhalconWings = PhalconWings;
    $(document).ready(function(){
        PhalconWings.renderDataGrid();
        PhalconWings.fetchActions();
    });
})(jQuery);