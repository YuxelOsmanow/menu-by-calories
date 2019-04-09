<?php

/* ==========================================================================
    #Return Meals Array of by Criteria
========================================================================== */

function get_meals_id_sorted( $protein_type, $vegs ) {
    $meals_args = array(
        'post_type' => 'nxt_meal',
        'posts_per_page' => -1,
    );

    if ( !empty( $protein_type ) ) {
        $meta_query = array (
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'nxt_protein_source',
                    'value' => $protein_type,
                    'compare' => 'IN',
                ),
            )
        );
    }

    if ( !empty( $vegs ) ) {
        $vegs_query = array(
            'key' => 'nxt_vegetables',
            'value' => $vegs,
            'compare' => 'IN',
        );

        array_push( $meta_query[ 'meta_query' ], $vegs_query);
    }

    $meals_args[ 'meta_query' ] = $meta_query;
    $meals = new WP_Query( $meals_args );
    $meals_array = $meals->posts;
    $meals_sorted = array();

    foreach ( $meals_array as $meal) {
        $meals_sorted[] = $meal->ID;
    }

    return $meals_sorted;
}

/* ==========================================================================
    #Make the Daily Menu
========================================================================== */

function get_daily_menu( $meal_ids, $total_calories ) {

    if ( empty( $meal_ids ) ) {
        return __( 'Meals Array is Empty', 'nxt' );
    }

    if ( empty( $total_calories ) ) {
        return __( 'Please Provide Total Calories per Day', 'nxt' );
    }

    $one_day_menu = get_the_breakfast_and_lunch( $meal_ids, $total_calories );
    $one_day_menu = get_the_dinner( $one_day_menu, $meal_ids, $one_day_menu[ 'remaining_calories' ], $total_calories );
    $one_day_menu[ 'brunch' ] = get_random_meal( $meal_ids, 'brunch', $one_day_menu[ 'after_dinner_remaining_calories' ] );

    return $one_day_menu;
}

/* ==========================================================================
    #Get the Breakfast and Lunch Meals
========================================================================== */

function get_the_breakfast_and_lunch( $meal_ids, $total_calories ) {
    $one_day_menu = array();

    $breakfast_meal = get_random_meal( $meal_ids, 'breakfast', $total_calories );
    $breakfast_meal_calories = get_meal_calories( $breakfast_meal );
    $remaining_calories = recalculate_remaining_calories( $total_calories, $breakfast_meal );
    $lunch_meal = get_random_meal( $meal_ids, 'lunch', $remaining_calories );
    $lunch_meal_calories = get_meal_calories( $lunch_meal );

    $max_dinner_calories = get_max_or_min_meal_time_calories( 'dinner' );
    $min_brunch_calories = get_max_or_min_meal_time_calories( 'brunch', 'ASC' );

    if ( ( $breakfast_meal_calories + $lunch_meal_calories ) > ( $max_dinner_calories + $min_brunch_calories ) ) {
        get_the_breakfast_and_lunch( $meal_ids, $total_calories );
    }

    $remaining_calories = recalculate_remaining_calories( $remaining_calories, $lunch_meal );
    $one_day_menu[ 'breakfast' ] = $breakfast_meal;
    $one_day_menu[ 'lunch' ] = $lunch_meal;
    $one_day_menu[ 'remaining_calories' ] = $remaining_calories;

    return $one_day_menu;
}

/* ==========================================================================
    #Get the Dinner Meal
========================================================================== */

function get_the_dinner( $one_day_menu, $meal_ids, $remaining_calories, $total_calories ) {
    $min_meal_time_calories = get_max_or_min_meal_time_calories( 'brunch', 'ASC' );
    $dinner_meal = get_random_meal( $meal_ids, 'dinner', ( $remaining_calories - $min_meal_time_calories ) );

    if ( !empty( $dinner_meal ) ) {
       $one_day_menu[ 'dinner' ] = $dinner_meal;
    } else {
       get_the_dinner( $one_day_menu, $meal_ids, $one_day_menu[ 'remaining_calories' ], $total_calories  );
    }

    $remaining_calories = recalculate_remaining_calories( $remaining_calories, $dinner_meal );
    $one_day_menu[ 'after_dinner_remaining_calories' ] = $remaining_calories;

    return $one_day_menu;
}

/* ==========================================================================
    #Get Random Meal
========================================================================== */

function get_random_meal( $meal_ids, $meal_time, $calories ) {
    if ( empty( $meal_ids ) ) {
        return;
    }

    if ( empty( $meal_time ) ) {
        return;
    }

    $meals_args = array(
        'post_type' => 'nxt_meal',
        'posts_per_page' => 1,
        'orderby' => 'rand',
        'post__in' => $meal_ids,
        'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_nxt_calories',
                    'value' => $calories,
                    'compare' => '<=',
                    'Type' => "NUMERIC"
                ),
                array(
                    'key' => 'nxt_select',
                    'value' => $meal_time,
                    'compare' => '=',
                ),
            )
    );

    $random_meal_query = new WP_Query( $meals_args );
    $random_meal = $random_meal_query->posts;

    if( empty( $random_meal ) || !is_array( $random_meal ) ){
        return null;
    }

    return $random_meal[0];
}

/* ==========================================================================
    #Get Meal Calories
========================================================================== */

function get_meal_calories( $meal ) {
    if ( empty( $meal ) ) {
       return;
    }

    $meal_calories = intval( carbon_get_post_meta( $meal->ID, 'nxt_calories' ) );

    return $meal_calories;
}

/* ==========================================================================
    #Re-calculate Remaining Day Calories
========================================================================== */

function recalculate_remaining_calories( $total_calories, $meal ) {
    if ( empty( $meal ) ) {
       return;
    }

    $meal_calories = get_meal_calories( $meal );

    return $total_calories - $meal_calories;
}

/* ==========================================================================
    #Get Max Meal Time Calories
========================================================================== */

function get_max_or_min_meal_time_calories( $meal_time, $order = null ) {
    if ( empty( $meal_time ) ) {
        return;
    }

    if ( empty( $order ) ) {
        $order = 'DESC';
    }

    $max_or_min_brunc_calories_query = new WP_Query( array(
        'post_type' => 'nxt_meal',
        'orderby' => 'meta_value_num',
        'order' => $order,
        'meta_key' => '_nxt_calories',
        'posts_per_page' => 1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'nxt_select',
                'value' => $meal_time,
                'compare' => '=',
            ),
        )
    ) );

    $max_or_min_brunc_calories_query = $max_or_min_brunc_calories_query->posts;

    if( empty( $max_or_min_brunc_calories_query ) || !is_array( $max_or_min_brunc_calories_query ) ){
        return null;
    }

    return get_meal_calories( $max_or_min_brunc_calories_query[0] );
}
