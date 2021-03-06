/**
 * External dependencies
 */
import React from 'react';
import Button from 'components/button';
import { translate as __ } from 'i18n-calypso';

/**
 * Internal dependencies
 */
import { imagePath } from 'constants';

const PlanHeader = React.createClass( {
	render() {
		let starrySky = '',
			planCard = '';
		switch ( this.props.plan ) {
			case 'jetpack_free':
				starrySky = (
					<div className="jp-jetpack-landing__header">
						<h2 className="jp-jetpack-landing__title">
							{ __( 'World class security and publishing tools.' ) }
						</h2>

						<p className="jp-jetpack-landing__description">
							{ __( 'Backup, protect, repair and build a better website.' ) }
						</p>

						<div className="jp-jetpack-landing__img-text">
							<div className="jp-jetpack-landing__column">
								<h2>{ __( "Threats don't discriminate" ) }</h2>
								<p>{ __( "Hackers, botnets and spammers attack websites indiscriminately. Their goal is to attack everywhere and often. Our goal is to help you prepare by blocking these threats, and in worst-case-scenarios we'll be here to help you restore your site to its former glory." ) }</p>
								<p>
									<Button href={ 'https://wordpress.com/plans/' + window.Initial_State.rawUrl }>
										{ __( 'Compare Plans' ) }
									</Button>
									<Button href={ 'https://wordpress.com/plans/' + window.Initial_State.rawUrl } className="is-primary">
										{ __( 'Upgrade Now' ) }
									</Button>
								</p>
							</div>
							<div className="jp-jetpack-landing__column">
								<img src={ imagePath + '/plans/admin-lock2x.png' } />
							</div>
						</div>
					</div>
				);
				planCard = (
					<div className="jp-jetpack-landing__plan-card">
						<img src={ imagePath + '/plans/free-plan-icon.jpg' } />
						<h3>{ __( 'Your site is on the Free Jetpack Plan' ) }</h3>
						<p>{ __( 'Upgrade to Premium or Pro in order to unlock world class security, anti-spam tools, and priority support.' ) }</p>
					</div>
				);
				break;

			case 'jetpack_premium':
			case 'jetpack_premium_monthly':
				planCard = (
					<div className="jp-jetpack-landing__plan-card">
						<img src={ imagePath + '/plans/premium-plan-icon.jpg' } />
						<h3>{ __( 'Your site is on the Premium Jetpack Plan' ) }</h3>
						<p>{ __( 'Unlock the full potential of your site with the premium features included in your plan.' ) }</p>
					</div>
				);
				break;

			case 'jetpack_business':
			case 'jetpack_business_monthly':
				planCard = (
					<div className="jp-jetpack-landing__plan-card">
						<img src={ imagePath + '/plans/pro-plan-icon.jpg' } />
						<h3>{ __( 'Your site is on the Professional Jetpack Plan' ) }</h3>
						<p>{ __( 'Unlock the full potential of your site with the professional features included in your plan.' ) }</p>
					</div>
				);
				break;

			case 'dev':
				planCard = (
					<div className="jp-jetpack-landing__plan-card">
						<img src={ imagePath + '/plans/free-plan-icon.jpg' } />
						<h3>{ __( 'Your site is on Development Mode' ) }</h3>
						<p>{ __( 'Once you connect, you can upgrade to Premium or Pro in order to unlock worldclass security, anti-spam tools, and priority support.' ) }</p>
					</div>
				);
				break;
		}
		return (
			<div>
				{ starrySky }
				{ planCard	}
			</div>
		);
	}
} );

export default PlanHeader;