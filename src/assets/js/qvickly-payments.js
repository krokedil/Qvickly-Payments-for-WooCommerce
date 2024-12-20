jQuery( function ( $ ) {
    if ( typeof QvicklyPaymentsParams === "undefined" ) {
        return false
    }

    const gatewayParams = QvicklyPaymentsParams
    const { gatewayId, sessionId } = gatewayParams

    /**
     * Handles the process of proceeding with Qvickly Payments for an order.
     *
     * @param {string} orderId - The key of the order.
     * @param {Object} customerData - The customer data.
     * @returns {void}
     */
    const handleProceedWithQvickly = async ( orderId, customerData ) => {
        blockUI()
        try {
            const authArgs = { customer: { ...customerData }, sessionId }

            if ( authResponse ) {
                if ( state === "authorized" ) {
                    const authToken = authResponse.authorizationToken
                    const { state } = authResponse
                    const { createOrderUrl, createOrderNonce } = gatewayParams

                    $.ajax( {
                        type: "POST",
                        url: createOrderUrl,
                        dataType: "json",
                        data: {
                            state,
                            order_key: orderId,
                            auth_token: authToken,
                            nonce: createOrderNonce,
                        },
                        success: ( data ) => {
                            const {
                                data: { location },
                            } = data
                            window.location = location
                        },
                        error: ( jqXHR, textStatus, errorThrown ) => {
                            console.debug( "Error:", textStatus, errorThrown )
                            console.debug( "Response:", jqXHR.responseText )

                            submitOrderFail(
                                "createOrder",
                                "The payment was successful, but the order could not be created.",
                            )
                        },
                    } )
                } else if ( state === "awaitingSignatory" ) {
                    const { pendingPaymentUrl, pendingPaymentNonce } = gatewayParams
                    $.ajax( {
                        type: "POST",
                        url: pendingPaymentUrl,
                        dataType: "json",
                        data: {
                            order_key: orderId,
                            nonce: pendingPaymentNonce,
                        },
                        success: ( data ) => {
                            const {
                                data: { location },
                            } = data
                            window.location = location
                        },
                        error: ( jqXHR, textStatus, errorThrown ) => {
                            console.debug( "Error:", textStatus, errorThrown )
                            console.debug( "Response:", jqXHR.responseText )

                            submitOrderFail(
                                "pendingPayment",
                                "The payment is pending payment. Failed to redirect to order received page.",
                            )
                        },
                    } )
                }

                // redirect the user to a success page
            }
        } catch ( error ) {
            unblockUI()
        }
    }

    const printNotice = ( message ) => {
        const elementId = `${ gatewayId }-error-notice`

        // Remove any existing notice that we have created. This won't remove the default WooCommerce notices.
        $( `#${ elementId }` ).remove()

        const html = `<div id='${ elementId }' class='woocommerce-NoticeGroup'><ul class='woocommerce-error' role='alert'><li>${ message }</li></ul></div>`
        $( "form.checkout" ).prepend( html )

        document.getElementById( elementId ).scrollIntoView( { behavior: "smooth" } )
    }

    const logToFile = ( message, level = "notice" ) => {
        const { logToFileUrl, logToFileNonce, reference } = gatewayParams
        console.debug( message )

        $.ajax( {
            url: logToFileUrl,
            type: "POST",
            dataType: "json",
            data: {
                level,
                reference,
                message: message,
                nonce: logToFileNonce,
            },
        } )
    }

    const unblockUI = () => {
        $( ".woocommerce-checkout-review-order-table" ).unblock()
        $( "form.checkout" ).removeClass( "processing" ).unblock()
    }

    const blockUI = () => {
        /* Order review. */
        $( ".woocommerce-checkout-review-order-table" ).block( {
            message: null,
            overlayCSS: {
                background: "#fff",
                opacity: 0.6,
            },
        } )

        $( "form.checkout" ).addClass( "processing" )
        $( "form.checkout" ).block( {
            message: null,
            overlayCSS: {
                background: "#fff",
                opacity: 0.6,
            },
        } )
    }

    const isActiveGateway = () => {
        if ( $( 'input[name="payment_method"]:checked' ).length ) {
            const currentGateway = $( 'input[name="payment_method"]:checked' ).val()
            return currentGateway.indexOf( gatewayId ) >= 0
        }

        return false
    }

    /**
     * Update the nonce values.
     *
     * This is required when a guest user is logged in and the nonce values are updated since the nonce is associated with the user ID (0 for guests).
     *
     * @param {object} nonce An object containing the new nonce values.
     */
    const updateNonce = ( nonce ) => {
        for ( const key in nonce ) {
            if ( key in gatewayParams ) {
                gatewayParams[ key ] = nonce[ key ]
            }
        }
    }

    const submitOrderFail = ( error, message ) => {
        console.debug( "[%s] Woo failed to create the order. Reason: %s", error, message )

        printNotice( message )
        unblockUI()
        $( document.body ).trigger( "checkout_error" )
        $( document.body ).trigger( "update_checkout" )
    }

    const submitOrder = ( e ) => {
        if ( $( "form.checkout" ).is( ".processing" ) ) {
            return false
        }

        e.preventDefault()
        blockUI()

        const { submitOrderUrl } = gatewayParams
        $.ajax( {
            type: "POST",
            url: submitOrderUrl,
            data: $( "form.checkout" ).serialize(),
            dataType: "json",
            success: async ( data ) => {
                try {
                    if ( data.nonce ) {
                        updateNonce( data.nonce )
                    }

                    if ( "success" === data.result ) {
                        const { order_key: orderId, customer } = data

                        logToFile( `Successfully placed order ${ orderId }. Sending "shouldProceed: true".` )

                        await handleProceedWithQvickly( orderId, customer )
                    } else {
                        console.warn( "AJAX request succeeded, but the Woo order was not created.", data )
                        throw "SubmitOrder failed"
                    }
                } catch ( err ) {
                    console.error( err )
                    if ( data.messages ) {
                        // Strip HTML code from messages.
                        const messages = data.messages.replace( /<\/?[^>]+(>|$)/g, "" )

                        logToFile( "Checkout error | " + messages, "error" )
                        submitOrderFail( "submitOrder", messages )
                    } else {
                        logToFile( "Checkout error | No message", "error" )
                        submitOrderFail( "submitOrder", "Checkout error" )
                    }
                }
            },
            error: ( data ) => {
                try {
                    logToFile( "AJAX error | " + JSON.stringify( data ), "error" )
                } catch ( e ) {
                    logToFile( "AJAX error | Failed to parse error message.", "error" )
                }
                submitOrderFail( "AJAX", "Something went wrong, please try again." )
            },
        } )
    }

    let field = $( "#billing_company_number_field" ).detach()
    const moveCompanyNumberField = () => {
        if ( gatewayParams.companyNumberPlacement === "billing_form" ) {
            if ( isActiveGateway() ) {
                $( "#billing_company_number_field" ).detach()
                field.insertAfter( "#billing_company_field" )
            } else {
                field = $( "#billing_company_number_field" ).detach()
            }
        }
    }

    $( "body" ).on( "click", "input#place_order, button#place_order", ( e ) => {
        if ( ! isActiveGateway() ) {
            return
        }

        const organizationNumber = $( "#billing_company_number" ).val().trim()
        if ( organizationNumber.length === 0 ) {
            printNotice( gatewayParams.i18n.companyNumberMissing )
            return false
        }

        submitOrder( e )
    } )

    $( document ).ready( () => {
        // If "billing_form", remove the field from the payment_form and insert it after the company name field. Otherwise, if it is "payment_form", leave as-is.
        if ( gatewayParams.companyNumberPlacement === "billing_form" ) {
            if ( isActiveGateway() ) {
                $( "#billing_company_number_field" ).detach().insertAfter( "#billing_company_field" )
            }

            // Required whenever the customer changes payment method.
            $( "body" ).on( "change", 'input[name="payment_method"]', moveCompanyNumberField )
            // Required when the checkout is initially loaded, and Qvickly is the chosen gateway.
            $( "body" ).on( "updated_checkout", moveCompanyNumberField )
        }
    } )
} )
