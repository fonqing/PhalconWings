;(function($){
    /**
     * Check component requirements
     */
    if( 'undefined' == typeof($) ){
        alert('PhalconWings require "jQuery (1.8 +)"');
        return;
    }
    if( typeof($.fn.artDialog)=='undefined'){
        alert('PhalconWings require "artDialog(4.1.7) & artDialog iframeTools" Project!');
        return;
    }
    /**
     * PhalconWings Javascript Helper
     */
    var PhalconWings = {
        'version'   : '0.9.0',
        'author'    : 'Eric Won <fonqing@gmail.com>'
    };

    /**
     * To be removed
     */
    PhalconWings.previewImage = function(url , id){
        $('#'+id+'_pic').attr('src',url);
        $('#'+id).val(url);
    };

    /**
     * Show message layer on DOM
     *
     * @param {String} msg (The message to show)
     * @param {String} url (Redirect url)
     * @return void
     */
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

    /**
     * Send post request by AJAX
     *
     * @param {Object} o     (the virtual submit button Element object)
     * @param {String} msg   (Process success messge)
     * @param {String} link  (Process success forward address)
     * @return void
     */
    PhalconWings.doPost = function(o, msg, link){
        var form = $(o).closest('form');
        var url  = form.attr('action');
        $.post(url, form.serialize(), function(response){
            ( response.status == 1 ) ?
                PhalconWings.showMessage(msg, link) :
                art.dialog.alert(response.msg);
        }, 'json');
    };

    /**
     * Highlight the table style by javascript
     *
     * In modern browsers is not necessary,you can use CSS3 :hover
     *
     * @return void
     */
    PhalconWings.renderDataGrid = function()
    {
        $('.datatable .tb:odd').addClass('odd');
        $('.datatable .tb').hover(function(){
            $(this).addClass('on');
        },function(){
            $(this).removeClass('on');
        });
    };

    /**
     * Auto registe event to operating object
     */
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

    /**
     * Inject the PhalconWings namespace to window object
     */
    window.PhalconWings = PhalconWings;

    /**
     * Auto execute
     */
    $(document).ready(function(){
        //Auto render table styles
        PhalconWings.renderDataGrid();
        //Auto fetch operations
        PhalconWings.fetchActions();
    });

})(jQuery);