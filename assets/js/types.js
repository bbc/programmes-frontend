/* global bbcuser */
/** @typedef {{
 *
 * isSignedIn: function(): Promise<boolean>
 * getHashedId: function(): Promise<string|null>
 *
 * }} bbcuser */


/* global bbccookies */
/** @typedef {{
 *
 * cookiesEnabled: function(): boolean,
 * readPolicy: function(policyName:string): *,
 *
 * }} bbccookies
 */
