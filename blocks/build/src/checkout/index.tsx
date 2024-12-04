/**
 * External dependencies
 */
import React, { useEffect, useState, useRef } from "react";

/**
 * WordPress/WooCommerce dependencies
 */
import { decodeEntities } from "@wordpress/html-entities";
// @ts-ignore - Can't avoid this issue, but it's loaded in by Webpack
import { registerPaymentMethod } from "@woocommerce/blocks-registry";
// @ts-ignore - Can't avoid this issue, but it's loaded in by Webpack
import { getSetting } from "@woocommerce/settings";

declare global {
  interface Window {
    _qvicklyPayments: any;
    wc: any;
    qvickly: {
      payments: {
        api: {
          authorize: (args: any) => Promise<any>;
        };
      };
    };
  }
}

const settings: any = getSetting("qvickly_payments_data", {});
const title: string = settings.title || "Qvickly Payments";
const isEnabled: boolean = settings.enabled || false;
const description: string = settings.description || "";
const iconUrl = settings.iconurl || false;
const qvicklyPaymentsParams = settings.qvicklypaymentsparams || {};

const PaymentMethodComponent: React.FC<{ organizationNumber: React.RefObject<HTMLInputElement>; props: any }> = ({ organizationNumber, props }) => {
  const { onCheckoutSuccess } = props.eventRegistration;
  const { emitResponse } = props;
  const { billingData } = props.billing;
  const {shippingAddress } = props.shippingData;

  useEffect(() => {
    const unsubscribe = onCheckoutSuccess(async (orderData: any) => {
      const { orderId } = orderData;
      return await submitOrder(orderId, organizationNumber, billingData, shippingAddress, emitResponse);
    });
    return unsubscribe;
  }, [onCheckoutSuccess]);

  return null;
};

const submitOrder = async (
  orderId: any,
  organizationNumber: React.RefObject<HTMLInputElement>,
  billingData: any,
  shippingData: any,
  emitResponse: any
  ) => {
  const organizationNumberVal = organizationNumber.current?.value.trim();
  if (!organizationNumberVal || !organizationNumberVal.length) {
    return { type: emitResponse.responseTypes.ERROR, message: "Company number is missing.", messageContext: emitResponse.noticeContexts.CHECKOUT };
  }
  const { sessionId } = qvicklyPaymentsParams;
  const authArgs = extractCustomerData(billingData, shippingData, organizationNumberVal, sessionId);
  const authResponse = await window.qvickly.payments.api.authorize(authArgs);

  if (authResponse) {
    if ("authorized" === authResponse.state) {
      const authToken = authResponse.authorizationToken;
      const { state } = authResponse;
      const { createOrderUrl, createOrderNonce } = qvicklyPaymentsParams;

      try {
        const response = await fetch(createOrderUrl, {
          method: "POST",
          body: new URLSearchParams({
            state,
            order_key: orderId,
            auth_token: authToken,
            nonce: createOrderNonce,
          }),
        });
        const data = await response.json();
        const {
          data: { location },
        } = data;
        window.location = location;
        return { type: emitResponse.responseTypes.SUCCESS };
      } catch (error) {
        return { type: emitResponse.responseTypes.ERROR, message: "The payment was successful, but the order could not be created.", messageContext: emitResponse.noticeContexts.CHECKOUT };
      }
    } else if ("awaitingSignatory" === authResponse.state) {
      const { pendingPaymentUrl, pendingPaymentNonce } = qvicklyPaymentsParams;

      try {
        const response = await fetch(pendingPaymentUrl, {
          method: "POST",
          body: new URLSearchParams({
            order_key: orderId,
            nonce: pendingPaymentNonce,
          }),
        });
        const data = await response.json();
        const {
          data: { location },
        } = data;
        window.location = location;
        return { type: emitResponse.responseTypes.SUCCESS };
      } catch (error) {
        return { type: emitResponse.responseTypes.ERROR, message: "The payment is pending payment. Failed to redirect to order received page.", messageContext: emitResponse.noticeContexts.CHECKOUT };
      }
    }
    return { type: emitResponse.responseTypes.ERROR, message: "The payment was not successful. Not authorized.", messageContext: emitResponse.noticeContexts.CHECKOUT };
  }
  return { type: emitResponse.responseTypes.ERROR, message: "The payment was not successful. Not authorization response received.", messageContext: emitResponse.noticeContexts.CHECKOUT };
};

const extractCustomerData = (billingData: any, shippingData: any, organizationNumber: any, sessionId: any) => {
  return {
      customer: {
          companyId: organizationNumber || null,
          email: billingData?.email || null,
          firstName: billingData?.first_name || null,
          lastName: billingData?.last_name || null,
          phone: billingData?.phone || null,
          reference1: "",
          reference2: "",
          billingAddress: {
              attentionName: billingData?.first_name || null,
              city: billingData?.city || null,
              companyName: billingData?.company || null,
              country: billingData?.country || null,
              postalCode: billingData?.postcode || null,
              streetAddress: billingData?.address_1 || null
          },
          shippingAddress: {
              attentionName: shippingData?.first_name || null,
              city: shippingData?.city || null,
              companyName: shippingData?.company || null,
              country: shippingData?.country || null,
              postalCode: shippingData?.postcode || null,
              streetAddress: shippingData?.address_1 || null,
              contact: {
                  email: billingData?.email || null,
                  firstName: shippingData?.first_name || null,
                  lastName: shippingData?.last_name || null,
                  phone: shippingData?.phone || null
              }
          }
      },
      sessionId: sessionId || null
  };
};

const Notice: React.FC<{ message: string }> = ({ message }) => {
  const noticeRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (noticeRef.current) {
      noticeRef.current.scrollIntoView({ behavior: "smooth" });
    }
  }, [message]);

  return (
    <div ref={noticeRef} className="woocommerce-NoticeGroup">
      <ul className="woocommerce-error" role="alert">
        <li>{message}</li>
      </ul>
    </div>
  );
};

const Content: React.FC<any> = (props) => {
  const organizationNumber = useRef<HTMLInputElement>(null);

  return (
    <div>
      <p>{decodeEntities(description)}</p>
      <PaymentMethodComponent props={props} organizationNumber={organizationNumber} />
      <input
        type="text"
        className="input-text"
        name="billing_company_number_block"
        id="billing_company_number_block"
        placeholder="Company number"
        defaultValue=""
        ref={organizationNumber}
        required
      />
    </div>
  );
};

const Label: React.FC = () => {
  const icon = iconUrl ? <img src={iconUrl} alt={title} /> : null;
  return (
    <span className="qp-block-label">
      {icon}
      {title}
    </span>
  );
};

const options = {
  name: "qvickly_payments",
  label: <Label />,
  content: <Content />,
  edit: <Content />,
  placeOrderButtonLabel: "Pay with Qvickly",
  canMakePayment: () => isEnabled,
  ariaLabel: title,
};

registerPaymentMethod(options);