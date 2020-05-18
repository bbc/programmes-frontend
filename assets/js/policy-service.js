define(function PolicyService() {
    return {
        /**
         * read a policy from bbccookies
         * @param {string} policyName
         * @returns {Promise<*|null>} null if not logged in, value as per policy
         */
        readPolicy: function(policyName) {
            return bbcuser.isSignedIn()
                .then(function(signedIn) {
                        if (!signedIn) return null;
                        var cookies = window.bbccookies;
                        if (!cookies) return null;
                        return cookies.cookiesEnabled() ? cookies.readPolicy(policyName) : null;
                    }
                )
        }
    }
});
