/**
 * Created by fedotov on 29.03.16.
 */


//Some global vars
glyph_opts = {
    map: {
        doc: "fa fa-file-text",
        docOpen: "fa fa-file-text",
        checkbox: "fa fa-square-o",
        checkboxSelected: "fa fa-check-square-o",
        checkboxUnknown: "fa fa-share-square-o",
        dragHelper: "fa fa-play",
        dropMarker: "fa fa-arrow-right",
        error: "fa fa-exclamation-triangle",
        expanderClosed: "fa fa-menu-right",
        expanderLazy: "fa fa-angle-right fa-fw",  // fa-plus-sign
        expanderOpen: "fa fa-angle-down fa-fw",  // fa-collapse-down
        folder: "fa fa-folder",
        folderOpen: "fa fa-folder-open",
        loading: "fa fa-refresh fa-spin fa-fw",
        // my icons:
        User: "fa fa-fw fa-user",
        Computer: "fa fa-fw fa-desktop",
        Group: "fa fa-fw fa-users",
        Contact: "fa fa-fw fa-credit-card"
    }
};
selected_node = "";


$(function() {
    //create folders tree
    $("#tree").fancytree({
        extensions : ["glyph", "persist", "wide"],
        glyph: glyph_opts,
        generateIds: true,
        idPrefix: "ftt_",
        cookieId: "ftt_",
        source : {
            url: "index.php",
            data: {
                act: "get_folders"
            }
        },
        lazyLoad: function (event, data) {
            data.result = {
                url: "index.php",
                data: {
                    act: "get_folders",
                    path: data.node.key
                }
            }
        },
        toggleEffect: {
            effect: "drop",
            options: {
                direction: "left"
            },
            duration: 200
        },
        wide: {
            iconWidth: "1em",
            iconSpacing: "0.5em",
            levelOfs: "1.5em"
        },
        persist: {
            expandLazy: true
        },
        strings: ft_strings,
        activate: function (e, data) {
            node = data.node;
            selected_node = node.key;
            $("#objects").fancytree(
                "option",
                "source",
                {
                    url: "index.php",
                    data: {act: "get_objects", path: selected_node},
                    success: function () {}
                }
            )

        }


    });

    $("#objects").fancytree({
        idPrefix:"fto_",
        cookieId:"fto_",
        strings: ft_strings,
        extensions: ["persist", "table", "edit", "glyph" ],
        glyph: glyph_opts,
        icon: function (e, data) {
            type = data.node.data.type;
            if (type) {
                return glyph_opts.map[type];
            }
        },
        activate: function (event, data) {
        },
        table: {
            indentation: 20,
            nodeColumnIdx: 1,
            checkboxColumnIdx: 0
        },
        renderColumns:function(e,data){
            var node = data.node,
                $tdList = $(node.tr).find(">td");
            $tdList.eq(2).text(node.data.dtype)
        },
        checkbox:true,
        selectMode: 2,
        source: []
    });

    //collapsing
    $("#navbar_tree_btn").click(function () {
        $("#main-row .collapse").collapse('toggle');
        $("#navbar_tree_btn").toggleClass('active');

    });
    
});






