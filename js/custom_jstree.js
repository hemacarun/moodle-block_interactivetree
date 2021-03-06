function interactive_jstree(Y, capability){
    $(window).resize(function () {
        var h = Math.max($(window).height() - 0, 420);
        $('#block_interactivetree_container, #block_interactivetree_data, #block_interactivetree_tree, #block_interactivetree_data .block_interactivetree_content')
        .height(h).filter('.default').css('lineHeight', h + 'px');
    }).resize();
    $('#block_interactivetree_tree')
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
                "default" : {
                    "icon":'none'
                }
            },
            'plugins': ['state', 'contextmenu', 'wholerow','types'],
            contextmenu: {
                "items": function () {
                    if (capability == 0) {
                        return {
                            "create":false,
                            "rename":false,
                            "delete":false
                        }
                    } else {
                        var tmp = $.jstree.defaults.contextmenu.items();
                        return tmp;
                    }
                }
            }
        })
        .bind("select_node.jstree", function (e, data) {
            var href = data.node.a_attr.href;
            $(".jstree-anchor").click(function() {
                document.location.href = this;
            });
        })

        .on('delete_node.jstree', function (e, data) {
            $.get( M.cfg.wwwroot + '/blocks/interactivetree/tree_node.php?operation=delete_node', {'id': data.node.id})
                .fail(function () {
                    data.instance.refresh();
                });
        })

        .on('create_node.jstree', function (e, data) {
            $.get( M.cfg.wwwroot + '/blocks/interactivetree/tree_node.php?operation=create_node',
                  {'id': data.node.parent, 'position': data.position, 'text': data.node.text})
                        .done(function (d) {
                            data.instance.set_id(data.node, d.id);
                        })
                        .fail(function () {
                            data.instance.refresh();
                        });
        })

        .on('rename_node.jstree', function (e, data) {
            $.get( M.cfg.wwwroot + '/blocks/interactivetree/tree_node.php?operation=rename_node',
                  {'id': data.node.id, 'text': data.text})
                        .fail(function () {
                            data.instance.refresh();
                        });
        })

        .on('changed.jstree', function (e, data) {
            if (data && data.selected && data.selected.length) {
                $.get( M.cfg.wwwroot + '/blocks/interactivetree/tree_node.php?operation=get_content&id=' + data.selected.join(':'),
                    function (d) {
                        $('#data .default').html(d.content).show();
                    });
            } else {
                $('#data .content').hide();
                $('#data .default').html('Select a file from the tree.').show();
            }
        });
}// end of function