<?php

namespace WordCamp\Budgets\Privacy;

use WP_Query;
use WordCamp\Budgets\Reimbursement_Requests;

defined( 'WPINC' ) || die();


add_filter( 'wp_privacy_personal_data_exporters', __NAMESPACE__ . '\register_personal_data_exporters' );
add_filter( 'wp_privacy_personal_data_erasers', __NAMESPACE__ . '\register_personal_data_erasers' );

/**
 * Registers the personal data eraser for each WordCamp post type
 * @param array $erasers
 *
 * @return array
 */
function register_personal_data_erasers( $erasers ) {
	/**
	 * We should not add a eraser for WordCamp post type, because it contains data which can be used for
	 * accounting or reference purpose.
	 *
	 * This is just an empty stub so that we do not create one.
	 */
	return $erasers;
}

/**
 * Registers the personal data exporter for each WordCamp post type.
 *
 * @param array $exporters
 *
 * @return array
 */
function register_personal_data_exporters( $exporters ) {
	$exporters['wcb-reimbursements'] = array(
		'exporter_friendly_name' => __( 'WordCamp Reimbursement Requests', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\reimbursements_exporter',
	);

	$exporters['wcb-vendor-payments'] = array(
		'exporter_friendly_name' => __( 'WordCamp Vendor Payment Requests', 'wordcamporg' ),
		'callback' => __NAMESPACE__ . '\vendor_payment_exporter',
	);

	return $exporters;
}

/**
 * Finds and exports personal data associated with an email address in a vendor payment request
 *
 * @param string $email_address
 * @param int $page
 *
 * @return array
 */
function vendor_payment_exporter( $email_address, $page) {

	$results = array(
		'data' => array(),
		'done' => true,
	);

	$user = get_user_by( 'email', $email_address );

	if ( empty( $user->ID ) ) {
		return $results;
	}

	$sponsor_invoices = wcb_get_post_wp_query( \WCP_Payment_Request::POST_TYPE, $page, $user->ID );

	if ( empty( $sponsor_invoices ) ) {
		return $results;
	}

	$data_to_export = array();
	foreach ( $sponsor_invoices->posts as $post ) {
		$sponsor_inv_exp_data = array();
		$meta = get_post_meta( $post->ID );

		$sponsor_inv_exp_data[] = [
			'name' => 'Title',
			'value' => $post->post_title,
		];
		$sponsor_inv_exp_data[] = [
			'name' => 'Date',
			'value' => $post->post_date,
		];

		$sponsor_inv_exp_data =
			array_merge( $sponsor_inv_exp_data, wcb_get_meta_details( $meta, '_camppayments_' ) );

		if ( ! empty( $sponsor_inv_exp_data ) ) {
			$data_to_export[] = array(
				'group_id' => \WCP_Payment_Request::POST_TYPE,
				'group_label' => 'WordCamp Sponsor Invoices',
				'item_id' => \WCP_Payment_Request::POST_TYPE . "-{$post->ID}",
				'data' => $sponsor_inv_exp_data,
			);
		}
	}

	$results[ 'done' ] = $sponsor_invoices->max_num_pages <= $page;
	$results[ 'data' ] = $data_to_export;

	return $results;
}

/**
 * Finds and exports personal data associated with an email address in a Reimbursement Request.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function reimbursements_exporter( $email_address, $page ) {

	$results = array(
		'data' => array(),
		'done' => true,
	);

	$user = get_user_by( 'email', $email_address );

	if ( empty( $user->ID ) ) {
		return $results;
	}

	$reimbursements = wcb_get_post_wp_query( Reimbursement_Requests\POST_TYPE, $page, $user->ID );

	if ( empty( $reimbursements ) ) {
		return $results;
	}

	$data_to_export = array();
	foreach ( $reimbursements->posts as $post ) {
		$reimbursement_data_to_export = array();
		$meta = get_post_meta( $post->ID );

		$reimbursement_data_to_export[] = [
			'name' => 'Title',
			'value' => $post->post_title,
		];
		$reimbursement_data_to_export[] = [
			'name' => 'Date',
			'value' => $post->post_date,
		];

		// meta fields
		$reimbursement_data_to_export =
			array_merge( $reimbursement_data_to_export, wcb_get_meta_details( $meta, '_wcbrr_' ) );


		if ( ! empty( $reimbursement_data_to_export ) ) {
			$data_to_export[] = array(
				'group_id'    => Reimbursement_Requests\POST_TYPE,
				'group_label' => 'WordCamp Reimbursement Request',
				'item_id'     => Reimbursement_Requests\POST_TYPE . "-{$post->ID}",
				'data'        => $reimbursement_data_to_export,
			);
		}
	}

	$results['done'] = $reimbursements->max_num_pages <= $page;
	$results[ 'data' ] = $data_to_export;

	return $results;
}

/**
 * @param $meta array meta object of post, as retrieved by `get_post_meta( $post->ID )`
 * @param $prefix string prefix for meta fields
 *
 * @return array Details of the reimbursement request
 */
function wcb_get_meta_details( $meta, $prefix ) {
	$meta_details = array();
	foreach ( wcb_get_meta_mapping( $prefix ) as $meta_field => $meta_field_name ) {
		$data = isset( $meta[ $meta_field ] ) ? $meta[ $meta_field ] : null;
		if ( ! empty( $data ) && is_array( $data ) && ! empty( $data[0] ) ) {
			$meta_details[] = [
				'name' => $meta_field_name,
				'value' => $meta [ $meta_field ][0],
			];
		}
	}
	return $meta_details;
}

function wcb_get_meta_mapping( $prefix = '' ) {
	return array(
		$prefix . 'name_of_payer'               => 'Payer Name',
		$prefix . 'currency'                    => 'Currency',
		$prefix . 'payment_method'              => 'Payment Method',

		// Payment Method - Direct Deposit
		$prefix . 'ach_bank_name'               => 'Bank Name',
		$prefix . 'ach_account_type'            => 'Account Type',
		$prefix . 'ach_routing_number'          => 'Routing Number',
		$prefix . 'ach_account_number'          => 'Account Number',
		$prefix . 'ach_account_holder_name'     => 'Account Holder Name',

		// Payment Method - Check
		$prefix . 'payable_to'                  => 'Payable To',
		$prefix . 'check_street_address'        => 'Street Address',
		$prefix . 'check_city'                  => 'City',
		$prefix . 'check_state'                 => 'State / Province',
		$prefix . 'check_zip_code'              => 'ZIP / Postal Code',
		$prefix . 'check_country'               => 'Country',

		// Payment Method - Wire
		$prefix . 'bank_name'                   => 'Beneficiary’s Bank Name',
		$prefix . 'bank_street_address'         => 'Beneficiary’s Bank Street Address',
		$prefix . 'bank_city'                   => 'Beneficiary’s Bank City',
		$prefix . 'bank_state'                  => 'Beneficiary’s Bank State / Province',
		$prefix . 'bank_zip_code'               => 'Beneficiary’s Bank ZIP / Postal Code',
		$prefix . 'bank_country_iso3166'        => 'Beneficiary’s Bank Country',
		$prefix . 'bank_bic'                    => 'Beneficiary’s Bank SWIFT BIC',
		$prefix . 'beneficiary_account_number'  => 'Beneficiary’s Account Number or IBAN',

		// Intermediary bank details
		$prefix . 'interm_bank_name'            => 'Intermediary Bank Name',
		$prefix . 'interm_bank_street_address'  => 'Intermediary Bank Street Address',
		$prefix . 'interm_bank_city'            => 'Intermediary Bank City',
		$prefix . 'interm_bank_state'           => 'Intermediary Bank State / Province',
		$prefix . 'interm_bank_zip_code'        => 'Intermediary Bank ZIP / Postal Code',
		$prefix . 'interm_bank_country_iso3166' => 'Intermediary Bank Country',
		$prefix . 'interm_bank_swift'           => 'Intermediary Bank SWIFT BIC',
		$prefix . 'interm_bank_account'         => 'Intermediary Bank Account',

		$prefix . 'beneficiary_name'            => 'Beneficiary’s Name',
		$prefix . 'beneficiary_street_address'  => 'Beneficiary’s Street Address',
		$prefix . 'beneficiary_city'            => 'Beneficiary’s City',
		$prefix . 'beneficiary_state'           => 'Beneficiary’s State / Province',
		$prefix . 'beneficiary_zip_code'        => 'Beneficiary’s ZIP / Postal Code',
		$prefix . 'beneficiary_country_iso3166' => 'Beneficiary’s Country',

		// Vendor payment fields
		$prefix . 'description'                 => 'Description',
		$prefix . 'general_notes'               => 'Notes',
	);
}

function wcb_get_post_wp_query ( $post_type, $page, $user_id ) {
	return new WP_Query( array(
		'post_type'      => $post_type,
		'post_status'    => 'any',
		'post_author'    => $user_id,
		'number_posts'   => - 1,
		'posts_per_page' => 20,
		'paged'          => $page,
	));
}
