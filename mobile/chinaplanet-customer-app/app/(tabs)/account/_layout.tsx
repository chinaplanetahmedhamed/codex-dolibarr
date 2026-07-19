import { Stack } from 'expo-router';

import { palette } from '@/src/theme';

export default function AccountLayout() {
  return (
    <Stack screenOptions={{ contentStyle: { backgroundColor: palette.background } }}>
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <Stack.Screen
        name="transactions"
        options={{
          title: 'كشف الحساب / Transactions',
          headerStyle: { backgroundColor: palette.navy },
          headerTintColor: palette.white,
          headerBackTitle: 'رجوع',
        }}
      />
    </Stack>
  );
}
