$(function () {
    $(window).resize(function () {
        var h = Math.max($(window).height() - 0, 420);
        $('#container, #data, #tree, #data .content').height(h).filter('.default').css('lineHeight', h + 'px');
    }).resize();

    $('#tree')
            .jstree({
                'core': {
                    'data': {
                        'url': M.cfg.wwwroot + '/blocks/interactivetree/tree_node.php?operation=get_node',
                        'data': function (node) {
                            return {'id':node.id }
                        }

                    },

                    'check_callback': true,
                    'themes': {
                        'responsive': false
                    }

                },
              
              "types" : {
    "#" : {
     
      "valid_children" : ["root"]
    },
    "root" : {
      "icon" :'none',
      "valid_children" : ["file"]
    },
  "default" : {
        "icon":'none',
      "valid_children" : []
    },
  },
    'plugins': ['state', 'wholerow','types'],
            })

       .bind("select_node.jstree", function (e, data) {
        var href = data.node.a_attr.href;
       $(".jstree-anchor").click(function()
       {
       document.location.href = this;
        });
        // document.location.href = href;
   })



            .on('delete_node.jstree', function (e, data) {
                $.get('blocks/interactivetree/tree_node.php?operation=delete_node', {'id': data.node.id})
                        .fail(function () {
                            data.instance.refresh();
                        });
            })
            .on('create_node.jstree', function (e, data) {
                $.get('blocks/interactivetree/tree_node.php?operation=create_node', {'id': data.node.parent, 'position': data.position, 'text': data.node.text})
                        .done(function (d) {
                            console.log(d);
                            data.instance.set_id(data.node, d.id);
                        })
                        .fail(function () {
                            data.instance.refresh();
                        });
            })
            .on('rename_node.jstree', function (e, data) {
                $.get('blocks/interactivetree/tree_node.php?operation=rename_node', {'id': data.node.id, 'text': data.text})
                        .fail(function () {
                            data.instance.refresh();
                        });
            })
            .on('move_node.jstree', function (e, data) {
                $.get('blocks/interactivetree/tree_node.php?operation=move_node', {'id': data.node.id, 'parent': data.parent, 'position': data.position})
                        .fail(function () {
                            data.instance.refresh();
                        });
            })
            .on('copy_node.jstree', function (e, data) {
                $.get('blocks/interactivetree/tree_node.php?operation=copy_node', {'id': data.original.id, 'parent': data.parent, 'position': data.position})
                        .always(function () {
                            data.instance.refresh();
                        });
            })
            .on('changed.jstree', function (e, data) {
                if (data && data.selected && data.selected.length) {
                    $.get('blocks/interactivetree/tree_node.php?operation=get_content&id=' + data.selected.join(':'), function (d) {
                         console.log(d.content);
                        $('#data .default').html(d.content).show();
                    });
                }
                else {
                    $('#data .content').hide();
                    $('#data .default').html('Select a file from the tree.').show();
                }
            });


});