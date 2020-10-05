const midcom_jqgrid_presets = {
    autowidth: true,
    altRows: true,
    altclass: 'even',
    deselectAfterSort: false,
    forceFit: true,
    gridview: true,
    headertitles: true,
    height: 'auto',
    hoverrows: true,
    shrinkToFit: true,
    sortable: true,
    autoencode: false,
    datatype: 'xml',
    iconSet: 'fontAwesome',
    jsonReader: {
        repeatitems: false,
        id: '0'
    }
};

$.jgrid.defaults = $.extend($.jgrid.defaults, midcom_jqgrid_presets);

$.jgrid.search.searchOnEnter = false;
$.jgrid.search.defaultSearch = 'cn';

const midcom_grid_resize = {
    timer: false,
    containment: '#content-text',
    firstrun: true,
    add_header_controls: function() {
        $('table.ui-jqgrid-btable').jqGrid('setGridParam', {
            onHeaderClick: function(gridstate) {
                $(this).closest('.ui-jqgrid').find('.ui-jqgrid-titlebar-maximize').toggle(gridstate === 'visible');
                $(window).trigger('resize');
        }});

        midcom_grid_resize.attach_maximizer($('.ui-jqgrid-titlebar'));
    },
    event_handler: function() {
        if (midcom_grid_resize.firstrun) {
            midcom_grid_resize.firstrun = false;
            midcom_grid_resize.add_header_controls();
        } else {
            if (!midcom_grid_resize.timer) {
                $(midcom_grid_resize.containment).addClass('openpsa-resizing');
            } else {
                clearTimeout(midcom_grid_resize.timer);
            }
            midcom_grid_resize.timer = setTimeout(midcom_grid_resize.end_resize, 300);
        }
        if ($('.ui-jqgrid-maximized').length > 0) {
            midcom_grid_resize.maximize_height($('.ui-jqgrid-maximized'));
            midcom_grid_resize.fill_width($('.ui-jqgrid-maximized'));
        } else {
            midcom_grid_resize.set_height($('.fill-height'), 'fill');
            midcom_grid_resize.set_height($('.crop-height'), 'crop');
            midcom_grid_resize.fill_width($('.full-width'));
        }
    },
    end_resize: function() {
        midcom_grid_resize.timer = false;
        $(midcom_grid_resize.containment).removeClass('openpsa-resizing');
    },
    attach_maximizer: function(items) {
        items.each(function() {
            $('<a role="link" class="ui-jqgrid-titlebar-maximize"><span class="fa fa-plus-circle"></span></a>')
                .on('click', function() {
                    var container = $(this).closest('.ui-jqgrid').parent();

                    if (container.hasClass('ui-jqgrid-maximized')) {
                        $(this).find('span').removeClass('fa-minus-circle').addClass('fa-plus-circle');
                        var jqgrid_id = container.find('table.ui-jqgrid-btable').attr('id'),
                            placeholder = $('#maximized_placeholder');

                        try {
                            $("#" + jqgrid_id).jqGrid().setGridHeight(placeholder.data('orig_height'));
                        } catch(e){}

                        container
                            .detach()
                            .removeClass('ui-jqgrid-maximized')
                            .insertBefore(placeholder)
                            .find('.ui-jqgrid-titlebar-close').show();
                        placeholder.remove();
                        //no container is maximized
                        $(midcom_grid_resize.containment).children().removeClass('ui-jqgrid-maximized');
                        $(midcom_grid_resize.containment).find('.ui-jqgrid-titlebar-maximize').show();
                        $(midcom_grid_resize.containment).children().removeClass('ui-jqgrid-maximized-background');
                        $(midcom_grid_resize.containment).css('overflow', 'auto');
                    } else {
                        $(this).find('span').removeClass('fa-plus-circle').addClass('fa-minus-circle')
                        $(midcom_grid_resize.containment).scrollTop(0);
                        $('<div id="maximized_placeholder"></div>')
                            .data('orig_height', container.find('.ui-jqgrid-bdiv').outerHeight())
                            .insertAfter(container);
                        container
                            .detach()
                            .removeClass('ui-jqgrid-maximized-background')
                            .addClass('ui-jqgrid-maximized')
                            .prependTo($(midcom_grid_resize.containment))
                            .find('.ui-jqgrid-titlebar-close').hide();
                        $(midcom_grid_resize.containment).children(':not(:first-child)').addClass('ui-jqgrid-maximized-background');
                        $(midcom_grid_resize.containment).css('overflow', 'hidden');
                    }
                    $(window).trigger('resize');
                })
                .hover(function() {
                    $(this).addClass('ui-state-hover');
                }, function() {
                    $(this).removeClass('ui-state-hover');
                })
                .appendTo($(this));
            if ($(this).closest('.ui-jqgrid').find('.ui-jqgrid-btable').data('maximized')) {
                $(this).find('.ui-jqgrid-titlebar-maximize').trigger('click');
            }
            if ($(this).closest('.ui-jqgrid').find('.ui-jqgrid-btable').is(':hidden')) {
                $(this).find('.ui-jqgrid-titlebar-maximize').hide();
            }
        });
    },
    fill_width: function(items) {
        if (items.length === 0) {
            return;
        }

        var new_width;

        items.each(function(index, item) {
            if (items.hasClass('ui-jqgrid-maximized')) {
                new_width = $(midcom_grid_resize.containment).width();
            } else {
                //calculate for each item separately to take care of floating neighbors
                new_width = $(item).width();
            }
            $(item).find('.ui-jqgrid table.ui-jqgrid-btable').each(function() {
                panel = $("#gbox_" + this.id).closest('.ui-tabs-panel');
                if (   panel.length > 0
                    && panel.hasClass('ui-tabs-hide')) {
                    return;
                }
                try {
                    var old_width = $(this).jqGrid().getGridParam('width');
                    if (!$(this).data('resized') || new_width != old_width) {
                        $(this).jqGrid().setGridWidth(new_width);
                        $(this).data('resized', true);
                    }
                } catch(e){}
            });
        });
    },
    set_height: function(items, mode) {
        if (items.length === 0 || $(midcom_grid_resize.containment).length === 0) {
            return;
        }

        var grids_content_height = 0,
            container_height = $(midcom_grid_resize.containment).height(),
            container_nongrid_height = 0,
            visible_grids = 0,
            grid_heights = {},
            minimum_height = 21;

        if ($('#org_openpsa_resize_marker_end').length === 0) {
            $(midcom_grid_resize.containment)
                .append('<div id="org_openpsa_resize_marker_end"></div>')
                .prepend('<div id="org_openpsa_resize_marker_start"></div>');
        }
        container_nongrid_height = $('#org_openpsa_resize_marker_end').position().top - $('#org_openpsa_resize_marker_start').position().top;

        items.each(function() {
            var grid_body = $("table.ui-jqgrid-btable", $(this));
            if (grid_body.length > 0) {
                var grid_height = grid_body.parent().parent().height(),
                    content_height = grid_body.outerHeight();
                if (    content_height === 0
                    && (   grid_body.jqGrid('getGridParam', 'datatype') !== 'local'
                        || (   grid_body.jqGrid('getGridParam', 'treeGrid') === true
                            && grid_body.jqGrid('getGridParam', 'treedatatype') !== 'local'))) {
                    content_height = 100;
                }

                if (   grid_body.jqGrid('getGridParam', 'gridstate') === 'visible'
                    && $(this).is(':visible')) {
                    grid_heights[grid_body.attr('id')] = content_height;
                    grids_content_height += content_height;
                    container_nongrid_height -= grid_height;
                    visible_grids++;
                }
            }
        });

        var available_space = container_height - container_nongrid_height;

        if (   grids_content_height === 0
            || available_space <= minimum_height * visible_grids) {
            return;
        }

        if (available_space > grids_content_height && mode !== 'fill') {
            $.each(grid_heights, function(grid_id, content_height) {
                set_param(grid_id, content_height);
            });
            return;
        }

        $.each(grid_heights, function(grid_id, content_height) {
            var new_height = available_space * (content_height / grids_content_height);
            if (new_height < minimum_height) {
                available_space -= minimum_height;
                grids_content_height -= content_height;
                set_param(grid_id, minimum_height);
                delete grid_heights[grid_id];
            }
        });

        $.each(grid_heights, function(grid_id, content_height) {
            var new_height = Math.floor(available_space * (content_height / grids_content_height));
            set_param(grid_id, new_height);
        });

        function set_param(grid_id, value) {
            let grid = $("#" + grid_id);
            if (grid.parent().parent().height() !== value) {
                try {
                    grid.jqGrid().setGridHeight(value);
                } catch(e){}
                if (grid.data('vScroll')) {
                    grid.closest(".ui-jqgrid-bdiv").scrollTop(grid.data('vScroll'));
                    grid.removeData('vScroll');
                }
            }
        }
    },
    maximize_height: function(part) {
        var part_height = part.outerHeight(true),
            grid_height = $("table.ui-jqgrid-btable", part).parent().parent().outerHeight(),
            new_height = $(midcom_grid_resize.containment).height() + grid_height - part_height;

        try {
            $("table.ui-jqgrid-btable", part).jqGrid().setGridHeight(new_height);
        }
        catch(e){}
    }
};
$(window).resize(midcom_grid_resize.event_handler);

const midcom_grid_editable = {
    grid_id: '',
    last_added_row: 0,
    default_options: {
        keys: true,
        afterrestorefunc: function(id) {
            midcom_grid_editable.toggle(id, false);
        },
        aftersavefunc: function(id, response) {
            //if saved row was new_... then refresh tr-id
            if (response.responseText !== undefined) {
                var return_values = JSON.parse(response.responseText),
                    oldId = return_values.oldid;
                if (oldId.substring(0, 4) === 'new_') {
                    var pos = $('#' + midcom_grid_editable.grid_id + ' tr[id="' + oldId + '"]').prevAll().length,
                        newrow = $('#' + oldId);
                    id = return_values.id;

                    midcom_grid_editable.saveSingleItemPosition(id, pos);

                    newrow.attr('id', id);
                    newrow.find('.row_edit, .row_save, .row_cancel, .row_delete').data('row-id', id);
                }
            }

            midcom_grid_editable.toggle(id, false);
        },
        oneditfunc: function(id) {
            midcom_grid_editable.toggle(id, true);
        },
        successfunc: function(data) {
            var return_values = JSON.parse(data.responseText);
            return [true, return_values, return_values.id];
        },
        enable_sorting: false
    },
    toggle: function(id, edit_mode) {
        $("#" + id).find(".row_edit, .row_delete").toggleClass('hidden', edit_mode);
        $("#" + id).find(".row_save, .row_cancel").toggleClass('hidden', !edit_mode);
        $('#' + id).toggleClass('jqgrid-editing', edit_mode);

        this.toggle_mouselistener();
    },

    enable_inline: function (grid_id, custom_options) {
        var lastsel,
            self = this;
        self.options = $.extend({}, self.default_options, custom_options);
        self.grid_id = grid_id;
        $('#' + grid_id).jqGrid('setGridParam', {
            onSelectRow: function(id) {
                if (id && id !== lastsel) {
                    $('#' + id).restoreRow(lastsel);
                    lastsel = id;
                }
                if (!$('#' + id).hasClass('jqgrid-editing')) {
                    self.editRow(id);
                }
            }
        });

        self.add_inline_controls();
        var create_button_parameters = {
            caption: self.options.button_label || "",
            buttonicon: "fa-plus",
            onClickButton: function() {
                var new_id = 'new_' + self.last_added_row++,
                    params = {};

                if (self.options.enable_sorting) {
                    params.position = $('#' + grid_id + ' td[aria-describedby="invoice_items_position"]').length + 1;
                }
                //create new row; now with a position-value
                $('#' + self.grid_id).jqGrid('addRowData', new_id, params, 'last');

                self.render_buttons(new_id);
            }
        };
        $('#' + grid_id)
            .jqGrid('navGrid', "#p_" + grid_id, {add: false, del: false, refresh: false, edit: false, search: false})
            .jqGrid('navButtonAdd', "#p_" + grid_id, create_button_parameters);

        if (self.options.enable_sorting) {
            $('#' + grid_id)
                .on("sortstop", function() {
                    self.refreshItemPositions();
                    //Refresh the rows alternately with the style from the class even
                    $(this).find("tbody tr.jqgrow").removeClass('even');
                    $(this).find("tbody tr.jqgrow:visible:odd").addClass('even');
                })
                .jqGrid("sortableRows", {helper: "clone"});
        }
    },
    /**
     * This function works only if enable_sorting is set true
     */
    toggle_mouselistener: function() {
        if (this.options.enable_sorting) {
            var isEdit = $('#' + this.grid_id + ' tr.jqgrid-editing').length > 0;

            if (isEdit) {
                $('#' + this.grid_id).sortable("disable");
                $('#' + this.grid_id + ' tbody > .jqgrow').enableSelection();
            } else {
                $('#' + this.grid_id).sortable("enable");
                $('#' + this.grid_id + ' tbody > .jqgrow').disableSelection();
            }
        }
    },
    editRow: function(id) {
        $('#' + this.grid_id).jqGrid('editRow', id, this.options);
        $('#cancel_button_' + id).closest("tr")
            .find('textarea, input[type="text"]').filter(':visible').first().focus();
    },
    saveRow: function(id) {
        $('#' + this.grid_id).jqGrid('saveRow', id, this.options);
    },
    restoreRow: function(id) {
        $('#' + this.grid_id).jqGrid('restoreRow', id, this.options);
    },
    deleteRow: function(id) {
        var edit_url = $('#' + this.grid_id).jqGrid('getGridParam', 'editurl'),
            rowdata = $('#' + this.grid_id).jqGrid('getRowData', id),
            self = this,
            callAfterSave = function() {
                $('#' + self.grid_id).jqGrid('delRowData', id);
                if (typeof self.options.afterdeletefunc === 'function') {
                    self.options.afterdeletefunc();
                }
                if (self.options.enable_sorting) {
                    self.refreshItemPositions();
                }
            };
        rowdata.oper = 'del';

        if (rowdata.id == '') {
            callAfterSave();
        } else {
            $.post(edit_url, rowdata, function() {
                callAfterSave();
            });
        }
    },
    refreshItemPositions: function() {
        var self = this;
        $('#' + this.grid_id + ' td[aria-describedby="invoice_items_position"]').each(function(index) {
            var idx = index + 1,
                oldPos = parseInt($(this).text()),
                trId = this.parentNode.id;

            if (idx !== oldPos) {
                // Set new Position-Number in this td
                $(this).html(idx);

                if (trId.substring(0, 4) !== 'new_') {
                    self.saveSingleItemPosition(trId, idx);
                }
            }
        });
    },
    saveSingleItemPosition: function(id, pos) {
        $.ajax({
            type: 'POST',
            url: this.options.position_url,
            data: {id: id, position: pos}
        });
    },
    render_buttons: function(rowid) {
        var be = "<i class='row_button row_edit fa fa-pencil' data-row-id='" + rowid + "'></i>",
            bs = "<i class='row_button row_save hidden fa fa-check' data-row-id='" + rowid + "'></i>",
            bc = "<i class='row_button row_cancel hidden fa fa-ban' data-row-id='" + rowid + "'></i>",
            bd = "<i class='row_button row_delete fa fa-trash' data-row-id='" + rowid + "'></i>";
        $("#" + this.grid_id).jqGrid('setRowData', rowid, {actions: be + bs + bc + bd});
    },
    add_inline_controls: function() {
        var rowids = $("#" + this.grid_id).jqGrid('getDataIDs'),
            self = this,
            i;

        for (i = 0; i < rowids.length; i++) {
            this.render_buttons(rowids[i]);
        }

        $("#" + this.grid_id).on('click', ".row_edit", function() {
            self.editRow(this.dataset.rowId);
        });
        $("#" + this.grid_id).on('click', ".row_delete", function(e) {
            e.stopPropagation();
            self.deleteRow(this.dataset.rowId);
        });
        $("#" + this.grid_id).on('click', ".row_save", function(e) {
            e.stopPropagation();
            self.saveRow(this.dataset.rowId);
        });
        $("#" + this.grid_id).on('click', ".row_cancel", function(e) {
            e.stopPropagation();
            self.restoreRow(this.dataset.rowId);
        });
    }
};

const midcom_grid_footer = {
    set_field: function(grid_id, colname, operation) {
        var value = $('#' + grid_id).jqGrid('getCol', colname, false, operation),
            footerdata = {};
        footerdata[colname] = value;
        $('#' + grid_id).jqGrid('footerData', 'set', footerdata);
    }
};

const midcom_grid_helper = {
    event_handler_added: false,
    active_grids: [],
    maximized_grid: '',
    bind_grouping_switch: function(grid_id) {
        var grid = $("#" + grid_id),
            group_conf = grid.jqGrid('getGridParam', 'groupingView'),
            active = grid.jqGrid('getGridParam', 'grouping'),
            expand = false,
            toggle = $('<input type="button" value="-">')
                .on('click', function() {
                    var idPrefix = grid_id + "ghead_0_";

                    grid.find('.jqgroup').each(function(index, element) {
                        if (   (!expand && $(element).find('.tree-wrap').hasClass(group_conf.minusicon))
                            || (expand && $(element).find('.tree-wrap').hasClass(group_conf.plusicon))) {
                                     grid.jqGrid('groupingToggle', idPrefix + index);
                        }
                    });
                    expand = !expand;
                    toggle.val(expand ? '+' : '-');
                });

        $("#chgrouping_" + grid_id)
            .val((active) ? group_conf.groupField[0] : 'clear')
            .on('change', function() {
                var selection = $(this).val(),
                    previous = group_conf.groupField[0];

                if (selection) {
                    if (selection == "clear") {
                        grid.jqGrid('groupingRemove', true);
                    } else {
                        grid.jqGrid('groupingGroupBy', selection);
                    }

                    if (   selection !== previous
                        && !$(this).find('[value="' + previous + '"]').data('hidden')) {
                        // Workaround for https://github.com/tonytomov/jqGrid/issues/431
                        grid.jqGrid('showCol', previous);
                    }
                    $(window).trigger('resize');
                }
            })
            .after(toggle);
    },
    setup_grid: function (grid_id, config_orig) {
        var identifier = 'openpsa-jqgrid#' + grid_id,
            saved_values = {},
            config = $.extend(true, {}, config_orig);

        if (   typeof window.localStorage !== 'undefined'
            && window.localStorage) {
            midcom_grid_helper.active_grids.push(grid_id);
            saved_values = JSON.parse(window.localStorage.getItem(identifier));
            if (saved_values) {
                if (typeof saved_values.custom_keys !== 'undefined') {
                    var keys = saved_values.custom_keys;
                    delete saved_values.custom_keys;
                    $('#' + grid_id).data('vScroll', keys.vScroll);

                    //only allow one maximized
                    if (keys.maximized && midcom_grid_helper.maximized_grid == '') {
                        midcom_grid_helper.maximized_grid = grid_id;
                    } else {
                        keys.maximized = false;
                    }
                    $('#' + grid_id).data('maximized', keys.maximized);
                }
                config = $.extend(config, saved_values);
            }
            if (config.data) {
                // if data was removed since last visit, decrease page number
                if (config.data.length <= (config.rowNum * config.page)) {
                    config.page = Math.ceil(config.data.length / config.rowNum);
                }
                // if data was added to grid that was empty on last visit, increase page number
                else if (config.page === 0) {
                    config.page = 1;
                }
            }
            if (midcom_grid_helper.event_handler_added === false) {
                $(window).on('unload', midcom_grid_helper.save_grid_data);
                midcom_grid_helper.event_handler_added = true;
            }
        }

        try {
            $('#' + grid_id).jqGrid(config);
        } catch (exception) {
            if (!$.isEmptyObject(saved_values)) {
                $('#' + grid_id).jqGrid('GridUnload');
                $('#' + grid_id).jqGrid(config_orig);
            } else {
                throw exception;
            }
        }
    },
    save_grid_data: function() {
        var grid_maximized = false;
        midcom_grid_helper.active_grids.forEach(function(grid_id) {
            if (typeof $('#' + grid_id).jqGrid === 'undefined') {
                return;
            }
            var identifier = 'openpsa-jqgrid#' + grid_id,
                grid = $('#' + grid_id),
                data = {
                    page: grid.jqGrid('getGridParam', 'page'),
                    sortname: grid.jqGrid('getGridParam', 'sortname'),
                    sortorder: grid.jqGrid('getGridParam', 'sortorder'),
                    hiddengrid: grid.closest('.ui-jqgrid-view').find('.ui-jqgrid-titlebar-close .ui-icon').hasClass('ui-icon-circle-triangle-s'),
                    custom_keys: {
                        vScroll: grid.closest(".ui-jqgrid-bdiv").scrollTop(),
                        //only allow one maximized
                        maximized: (grid.closest('.ui-jqgrid-maximized').length > 0) && (grid_maximized == grid_id || grid_maximized == false)
                    }
                };

            if ($("#chgrouping_" + grid_id).length > 0) {
                data.grouping = grid.jqGrid('getGridParam', 'grouping');
                data.groupingView = grid.jqGrid('getGridParam', 'groupingView');

                delete data.groupingView.groups;
                delete data.groupingView.lastvalues;
            }

            if (data.custom_keys.maximized) {
                grid_maximized = grid_id;
            }

            if (   grid.jqGrid('getGridParam', 'scroll') === 1
                && data.custom_keys.vScroll < grid.height()) {
                data.page = 1;
            }
            window.localStorage.setItem(identifier, JSON.stringify(data));
        });
    }
};

const midcom_grid_csv = {
    configs: {},
    separator: ';',
    add: function (config) {
        this.configs[config.id] = config;

        $('button#' + config.id + '_export').on('click', function(e) {
            midcom_grid_csv.prepare_data(config);
            e.preventDefault();
        });
    },
    prepare_data: function(config) {
        var rows = $('#' + config.id).jqGrid('getRowData'),
            field,
            data = '';

        function trim(input) {
            return input
                .replace(/\n|\r/g, " ") // remove line breaks
                .replace(/\s+/g, " ") // Shorten long whitespace
                .replace(/^\s+/g, "") // strip leading ws
                .replace(/\s+$/g, "") // strip trailing ws
                .replace(/<\/?([a-z][a-z0-9]*)\b[^>]*>/gi, ''); //strip HTML tags
        }

        for (field in config.fields) {
            data += trim(config.fields[field]) + this.separator;
        }

        data += '\n';

        rows.forEach(function(row) {
            for (field in config.fields) {
                if (typeof row[field] !== 'undefined') {
                    data += trim(row[field]) + midcom_grid_csv.separator;
                }
            }
            data += '\n';
        });

        var blob = new Blob([data], {type: "application/csv;charset=utf-8"});
        saveAs(blob, config.filename + ".csv");
    }
};

const midcom_grid_batch_processing = {
    initialize: function(config) {
        $('#form_' + config.id).hide();

        var widgets_to_add = [],
            label = config.submit || 'Go',
            //build action form and associated widgets
            action_select = '<div class="action_select_div" id="' + config.id + '_batch">';
        action_select += '<select id="' + config.id + '_batch_select" class="action_select" name="action" size="1">';

        $.each(config.options, function(key, value) {
            action_select += '<option value="' + key + '" >' + value.label + '</option>';
            if (typeof value.widget_config !== 'undefined') {
                var widget_id = config.id + '__' + key;
                widgets_to_add.push({id: widget_id, insertAfter: '#' + config.id + '_batch_select', widget_config: value.widget_config});
            }
        });
        action_select += '</select><input type="submit" name="send" value="' + label + '" style="display: none" /></div>';
        $(action_select).appendTo($('#form_' + config.id));

        widgets_to_add.forEach(function(widget_conf) {
            midcom_helper_datamanager2_autocomplete.create_widget(widget_conf);
        });

        $('#' + config.id + '_batch_select').on('change', function(event) {
            var selected_option = $(event.target).val();
            if (selected_option === 'none') {
                $(event.target).nextAll('input[type="submit"]').hide();
            } else {
                $(event.target).nextAll('input[type="submit"]').show();
            }
            $('.ui-autocomplete-input').hide();
            $('#' + config.id + '_batch').css('display', 'inline');
            $('#' + config.id + '__' + selected_option + '_search_input').show();
        });

        //hook action select into grid so that it'll get shown when necessary
        $('#' + config.id).jqGrid('setGridParam', {
            onSelectRow: function(id) {
                if ($('#' + config.id).jqGrid('getGridParam', 'selarrrow').length === 0) {
                    $('#' + config.id + '_batch').parent().hide();
                } else {
                    $('#' + config.id + '_batch').parent().show();
                }
                $(window).trigger('resize');
            },
            onSelectAll: function(rowids, status) {
                if (!status) {
                    $('#' + config.id + '_batch').parent().hide();
                } else {
                    $('#' + config.id + '_batch').parent().show();
                }
                $(window).trigger('resize');
            }
        });

        // We use regular post instead of ajax to get browser's busy indicator
        $("#form_" + config.id).on('submit', function() {
            function add_array(field, data) {
                for (var i = 0; i < data.length; i++) {
                    $('<input type="hidden" name="' + field + '[]" value="' + data[i] + '" />')
                        .appendTo('#form_' + config.id);
                }
            }
            add_array('entries', $("#" + config.id).jqGrid('getGridParam', 'selarrrow'));

            var action = $("#form_" + config.id + ' select[name="action"]').val(),
                autocomplete = $("#" + config.id + '__' + action + '_selection');

            if (autocomplete.length > 0 && autocomplete.val().length) {
                add_array('selection', JSON.parse(autocomplete.val()));
            } else if (config.options[action].value) {
                $('<input type="hidden" name="value" value="' + config.options[action].value + '" />')
                    .appendTo('#form_' + config.id);
            }
        });
    }
};

const midcom_grid_row_actions = {
    update_totals: function(table, config) {
        var total = 0,
            row_sum,
            totals_field = table.closest('.ui-jqgrid-view').find('.ui-jqgrid-ftable .' + config.totals_field);

        table.find('tbody tr').not('.jqgfirstrow').each(function() {
            row_sum = parseFloat($(this).find('.' + config.totals_field).prev().text());
            if (isNaN(row_sum)) {
                return;
            }

            total += row_sum;
        });

        totals_field.text($.fn.fmatter.number(total, $.jgrid.locales[$.jgrid.defaults.locale].formatter));
    },

    process: function(button, action, config) {
        button.attr('disabled', 'disabled');
        var id = button.parent().parent().attr('id');
        $.post(config.url + action + '/', {id: id}, function(data) {
            if (data.success !== false) {
                var old_grid = button.closest('.ui-jqgrid-btable'),
                    regex = new RegExp(data.old_status),
                    row_data = old_grid.getRowData(id),
                    new_grid = $('#' + old_grid.attr('id').replace(regex, data.new_status));

                old_grid.delRowData(id);
                midcom_grid_row_actions.update_totals(old_grid, config);

                if (new_grid.length < 1) {
                    // Grid is not present yet, reload
                    window.location.reload();
                    return;
                }

                if (new_grid.jqGrid('getGridParam', 'datatype') === 'local') {
                    row_data.action = data.action;
                    data.updated.forEach(function(item) {
                        row_data[item[0]] = item[1];
                    });
                    new_grid.addRowData(row_data.id, row_data, "last");
                    midcom_grid_row_actions.update_totals(new_grid, config);
                } else {
                    new_grid.trigger('reloadGrid');
                }
                $(window).trigger('resize');
            }

            data.messages.forEach(function(message) {
                $.midcom_services_uimessage_add(message);
            });
        })
            .fail(function(response) {
                if (response.status === 403) {
                    // most probably our login session expired. reload
                    location.href = location.href;
                } else {
                    $.midcom_services_uimessage_add({
                        type: 'error',
                        title: response.statusText,
                        message: response.responseText
                    });
                }
            });
    },

    init: function(config) {
        config.actions.forEach(function(action) {
            $('#' + config.identifier + '.ui-jqgrid-btable')
                .on('click', 'button.' + action, function() {
                    midcom_grid_row_actions.process($(this), action, config);
                });
        });
    }
};
