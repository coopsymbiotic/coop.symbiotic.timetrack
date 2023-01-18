(function($) {
  "use strict";

  var
    ENTER_KEY = 13,
    SPACE_KEY = 32;

  CRM.menubar.initializeTimetrackSearch = function() {
    // $('input[name=qfKey]', '#crm-qsearch').attr('value', CRM.menubar.qfKey);

    $('#timetrack_search')
      .autocomplete({
        source: function(request, response) {
          //start spinning the civi logo
          CRM.menubar.spin(true);
          var
            option = $('input[name=quickSearchField]:checked'),
            params = {
              search: request.term
            };
          CRM.api3('Timetracktask', 'getquick', params).done(function(result) {
            var ret = [];

            if (result.values.length > 0) {
              // $('#crm-qsearch-input').autocomplete('widget').menu('option', 'disabled', false);
              $.each(result.values, function(k, v) {
                ret.push({value: v.id, label: v.case_subject + ' / ' + v.title});
              });
            } else {
              $('#timetrack_search').autocomplete('widget').menu('option', 'disabled', true);
              var msg = ts('Task not found.');
              ret.push({value: '0', label: msg});
            }
            response(ret);
            //stop spinning the civi logo
            CRM.menubar.spin(false);
            // CRM.menubar.close();
          });
        },
        focus: function (event, ui) {
          CRM.menubar.open('timetrack_items');

          // This is when an item is 'focussed' by keyboard up/down or mouse hover.
          // It is not the same as actually having focus, i.e. it is not :focus
          var lis = $(event.currentTarget).find('li[data-cid="' + ui.item.value + '"]');
          lis.children('div').addClass('ui-state-active');
          lis.siblings().children('div').removeClass('ui-state-active');
          // Returning false leaves the user-entered text as it was.
          return false;
        },
        select: function (event, ui) {
          if (ui.item.value > 0) {
            document.location = CRM.url('civicrm/contact/view', {reset: 1, cid: ui.item.value});
          }
          return false;
        },
        create: function() {
          $(this).autocomplete('widget').addClass('crm-quickSearch-results');
        }
      })
      .on('keyup change', function() {
        $(this).toggleClass('has-user-input', !!$(this).val());
      })
      .keyup(function(e) {
        // CRM.menubar.close();
        if (e.which === ENTER_KEY) {
          if (!$(this).val()) {
            CRM.menubar.open('timetrack_items');
          }
        }
      })
      .autocomplete("instance")._renderItem = function( ul, item ) {
        var uiMenuItemWrapper = $("<div class='ui-menu-item-uiMenuItemWrapper'>");
        if (item.value == 0) {
          // "No results"
          uiMenuItemWrapper.text(item.label);
        }
        else {
          uiMenuItemWrapper.append($('<div>')
            .attr('href', CRM.url('civicrm/contact/view/case', {reset: 1, cid: item.value}))
            .css({ display: 'block' })
            .text(item.label)
            .data('tid', item.value)
            .click(function(e) {
              CRM.alert('@todo punch-in: ' + $(this).data('tid'));
              if (e.ctrlKey || e.shiftKey || e.altKey) {
                // Special-clicking lets you open several tabs.
                e.stopPropagation();
              }
              else {
                // Fall back to original behaviour.
                e.preventDefault();
              }
            }));
        }

        return $( "<li class='ui-menu-item' data-cid=" + item.value + ">" )
          .append(uiMenuItemWrapper)
          .appendTo( ul );
      };
  };

  CRM.menubar.initializeTimetrackPunch = function() {
    $('#timetrack_punch')
      .keyup(function(e) {
        // CRM.menubar.close();
        if (e.which === ENTER_KEY) {
          e.preventDefault();
          e.stopPropagation();
          var params = {'parse_input': $(this).val()};
          CRM.api3('Timetrackpunch', 'create', params).done(function(result) {
            if (result.is_error) {
              var message = result.error_message;
              message = message.replace(/:fire:/, '');
              CRM.alert(message, '', 'error');
            }
            else {
              var message = result.values.message;
              message = message.replace(/:white_check_mark:/, '');
              message = message.replace(/:checkered_flag:/, '');
              CRM.alert(message, '', 'success');
              // @todo Add punch status in the menu without requiring a page reload
            }
          });

          if (!$(this).val()) {
            CRM.menubar.open('timetrack_items');
          }
        }
      });
  };

  CRM.menubar.initializeTimetrackPunchout = function() {
    $('#civicrm-menu').find(`[data-name='timetrack_current_punch'] a`).on('click', function(event) {
      event.preventDefault();
      CRM.api3('Timetrackpunch', 'punchout').done(function(result) {
        if (result.is_error) {
          CRM.alert(result.error_message, '', 'error');
        }
        else {
          var pid = result.id;
          var punch = result.values[pid];
          CRM.alert(ts('Punched-out of %1 (%2h)', {1: punch.case_subject + ' / ' + punch.ktask_title, 2: punch.duration_text}), '', 'success');
        }
      });
    });
  };

  $(document)
    .on('crmLoad', '#civicrm-menu', function() {
      if (CRM.vars && CRM.vars.timetrack && !CRM.menubar.getItem('timetrack')) {
        CRM.menubar.addItems(-1, null, [CRM.vars.timetrack.menu]);

        var placeholder = $.find(`[data-name='timetrack_punch'] > a > span`)[0].innerText;
        var $search = $.find(`[data-name='timetrack_punch']`)[0];
        $search.innerHTML = '<div style="background-color: #eee; padding: 4px;"><input type="text" size="30" id="timetrack_punch" placeholder="' + placeholder + '" style="padding: 4px;"></div>';
        CRM.menubar.initializeTimetrackPunch();

        CRM.menubar.initializeTimetrackPunchout();

        var $search = $.find(`[data-name='timetrack_search']`)[0];
        $search.innerHTML = '<form style="background-color: #eee; padding: 4px;"><input type="text" size="30" id="timetrack_search" placeholder="Search..." style="padding: 4px;"></form>';
        CRM.menubar.initializeTimetrackSearch();


        // Workaround for AngularJS pages, where the #hash value is important
        $('#civicrm-menu').on('click.smapi', function(e, item) {
          var parent_name = $(item).parent().data('name');

          if (typeof parent_name == 'string' && parent_name.substr(0, 5) == 'lang_') {
            var hash = window.location.hash;

            if (hash && hash != '#') {
              $(item).attr('href', $(item).attr('href') + hash);
            }
          }
        });
      }
    });

})(CRM.$);
