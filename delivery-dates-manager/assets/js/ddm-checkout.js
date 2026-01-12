(function($) {
    'use strict';

    var DDMCheckout = {
        settings: {},
        selectedZone: null,
        availableDates: [],
        fulfillmentMethod: 'delivery',

        init: function() {
            if (typeof ddm_checkout === 'undefined') {
                return;
            }

            this.settings = ddm_checkout.zone_settings || {};
            this.bindEvents();
            this.initDatepicker();
            this.handleInitialState();
        },

        bindEvents: function() {
            $(document).on('change', '#ddm_delivery_zone', this.onZoneChange.bind(this));
            $(document).on('change', 'input[name="ddm_fulfillment_method"]', this.onFulfillmentChange.bind(this));
            $(document.body).on('updated_checkout', this.onCheckoutUpdate.bind(this));
        },

        handleInitialState: function() {
            var checkedMethod = $('input[name="ddm_fulfillment_method"]:checked').val();
            this.fulfillmentMethod = checkedMethod || 'delivery';
            this.toggleZoneField();
        },

        onFulfillmentChange: function(e) {
            var self = this;
            this.fulfillmentMethod = $(e.target).val();
            
            $('#ddm_delivery_date').val('');
            this.availableDates = [];
            
            this.toggleZoneField();
            this.updateFulfillmentMethod(this.fulfillmentMethod);
            
            if (this.fulfillmentMethod === 'pickup') {
                this.loadPickupDates();
            } else {
                var zoneId = $('#ddm_delivery_zone').val();
                if (zoneId) {
                    this.loadZoneDates(zoneId);
                } else {
                    $('#ddm_delivery_date').prop('disabled', true);
                    $('#ddm_delivery_date').datepicker('refresh');
                }
            }
        },
        
        updateFulfillmentMethod: function(method) {
            $.ajax({
                url: ddm_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'ddm_set_fulfillment_method',
                    method: method,
                    nonce: ddm_checkout.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $(document.body).trigger('update_checkout');
                    }
                }
            });
        },

        toggleZoneField: function() {
            if (this.fulfillmentMethod === 'pickup') {
                $('#ddm_delivery_zone_field').hide();
                $('#ddm_delivery_zone').removeAttr('required');
            } else {
                $('#ddm_delivery_zone_field').show();
                $('#ddm_delivery_zone').attr('required', 'required');
            }
        },

        initDatepicker: function() {
            var self = this;

            $('#ddm_delivery_date').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0,
                maxDate: '+60d',
                beforeShowDay: function(date) {
                    return self.isDateAvailable(date);
                },
                onSelect: function(dateText) {
                    self.onDateSelect(dateText);
                }
            });
        },

        onZoneChange: function(e) {
            var zoneId = $(e.target).val();
            
            this.selectedZone = zoneId;
            $('#ddm_delivery_date').val('');
            this.availableDates = [];

            if (!zoneId) {
                $('#ddm_delivery_date').prop('disabled', true);
                $('#ddm_delivery_date').datepicker('refresh');
                return;
            }

            this.loadZoneDates(zoneId);
        },

        loadZoneDates: function(zoneId) {
            var self = this;
            
            $('#ddm_delivery_date_field').addClass('ddm-loading');

            $.ajax({
                url: ddm_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'ddm_get_zone_dates',
                    zone_id: zoneId,
                    nonce: ddm_checkout.nonce
                },
                success: function(response) {
                    $('#ddm_delivery_date_field').removeClass('ddm-loading');

                    if (response.success) {
                        self.availableDates = response.data.dates;
                        $('#ddm_delivery_date').prop('disabled', false);
                        $('#ddm_delivery_date').datepicker('refresh');

                        self.updateShipping(zoneId);

                        if (response.data.same_day_available) {
                            self.showSameDayBadge();
                        } else {
                            self.hideSameDayBadge();
                        }
                    }
                },
                error: function() {
                    $('#ddm_delivery_date_field').removeClass('ddm-loading');
                }
            });
        },

        loadPickupDates: function() {
            var self = this;
            var dates = [];
            var now = new Date();
            
            for (var i = 1; i <= 30; i++) {
                var date = new Date(now);
                date.setDate(date.getDate() + i);
                
                var dateString = date.getFullYear() + '-' + 
                    String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(date.getDate()).padStart(2, '0');
                
                var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                var label = dayNames[date.getDay()] + ', ' + monthNames[date.getMonth()] + ' ' + date.getDate();
                
                dates.push({
                    date: dateString,
                    label: label,
                    type: 'pickup'
                });
            }
            
            this.availableDates = dates;
            this.hideSameDayBadge();
            
            $('#ddm_delivery_date').prop('disabled', false);
            
            setTimeout(function() {
                $('#ddm_delivery_date').datepicker('refresh');
            }, 100);
        },

        isDateAvailable: function(date) {
            var dateString = $.datepicker.formatDate('yy-mm-dd', date);

            for (var i = 0; i < this.availableDates.length; i++) {
                if (this.availableDates[i].date === dateString) {
                    var cssClass = 'ddm-date-available';
                    if (this.availableDates[i].type === 'same_day') {
                        cssClass = 'ddm-date-sameday';
                    } else if (this.availableDates[i].type === 'pickup') {
                        cssClass = 'ddm-date-pickup';
                    }
                    return [true, cssClass, this.availableDates[i].label];
                }
            }

            return [false, 'ddm-date-unavailable', ''];
        },

        onDateSelect: function(dateText) {
            var selectedDate = null;

            for (var i = 0; i < this.availableDates.length; i++) {
                if (this.availableDates[i].date === dateText) {
                    selectedDate = this.availableDates[i];
                    break;
                }
            }

            if (selectedDate) {
                $('#ddm_delivery_type').val(selectedDate.type);
                
                if (selectedDate.type === 'same_day') {
                    this.showSameDayBadge();
                }
            }

            $(document.body).trigger('update_checkout');
        },

        updateShipping: function(zoneId) {
            $.ajax({
                url: ddm_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'ddm_update_shipping',
                    zone_id: zoneId,
                    nonce: ddm_checkout.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $(document.body).trigger('update_checkout');
                    }
                }
            });
        },

        showSameDayBadge: function() {
            if (!$('.ddm-same-day-badge').length) {
                $('<span class="ddm-same-day-badge">Same-Day Available</span>')
                    .appendTo('#ddm_delivery_zone_field label');
            }
        },

        hideSameDayBadge: function() {
            $('.ddm-same-day-badge').remove();
        },

        onCheckoutUpdate: function() {
            var self = this;
            
            setTimeout(function() {
                self.handleInitialState();
                
                if (self.fulfillmentMethod === 'pickup' && self.availableDates.length === 0) {
                    self.loadPickupDates();
                }
                
                $('#ddm_delivery_date').datepicker('refresh');
            }, 100);
        }
    };

    $(document).ready(function() {
        DDMCheckout.init();
    });

})(jQuery);
