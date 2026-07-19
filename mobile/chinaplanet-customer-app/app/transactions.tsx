import { Redirect } from 'expo-router';

export default function LegacyTransactionsRedirect() {
  return <Redirect href="/account/transactions" />;
}
