Promise.all([
  import('./consent-scopes.test.mjs'),
  import('./signature.test.mjs'),
  import('./m2-dialog.test.mjs'),
  import('./landing-copy.test.mjs'),
  import('./legal.test.mjs'),
  import('./rename-contract.test.mjs'),
]).catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
