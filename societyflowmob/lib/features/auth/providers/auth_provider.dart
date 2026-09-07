import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../../../core/utils/storage_service.dart';
import '../models/user_model.dart';

class AuthState {
  final UserModel? user;
  final bool isLoading;
  final String? error;
  final String? devOtp;

  const AuthState({this.user, this.isLoading = false, this.error, this.devOtp});

  AuthState copyWith({UserModel? user, bool? isLoading, String? error, String? devOtp}) =>
      AuthState(
        user:      user      ?? this.user,
        isLoading: isLoading ?? this.isLoading,
        error:     error,
        devOtp:    devOtp    ?? this.devOtp,
      );
}

class AuthNotifier extends StateNotifier<AuthState> {
  AuthNotifier() : super(const AuthState());

  // ── Email/Password Login ──────────────────────────────────────────────────

  Future<void> login({required String email, required String password}) async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.post(
      ApiConfig.login,
      body: {'email': email, 'password': password},
    );
    if (result.success && result.data != null) {
      await _saveSession(result.data!);
    } else {
      state = state.copyWith(isLoading: false, error: result.message);
      throw Exception(result.message.isNotEmpty ? result.message : 'Invalid credentials');
    }
  }

  // ── Register / Account Activation ────────────────────────────────────────

  Future<void> register({
    required String email,
    required String phone,
    required String password,
  }) async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.post(
      ApiConfig.register,
      body: {
        'email':                 email,
        'phone':                 phone,
        'password':              password,
        'password_confirmation': password,
      },
    );
    if (result.success && result.data != null) {
      await _saveSession(result.data!);
    } else {
      state = state.copyWith(isLoading: false, error: result.message);
      throw Exception(result.message.isNotEmpty ? result.message : 'Registration failed');
    }
  }

  // ── Check Account (Step 1 of register flow) ───────────────────────────────

  Future<Map<String, dynamic>> checkAccount({
    required String email,
    required String phone,
  }) async {
    final result = await HttpService.instance.post(
      ApiConfig.checkAccount,
      body: {'email': email, 'phone': phone},
    );
    if (result.success && result.data != null) {
      return result.data!['data'] as Map<String, dynamic>? ?? {};
    }
    throw Exception(result.message.isNotEmpty ? result.message : 'Account not found');
  }

  // ── OTP Login ─────────────────────────────────────────────────────────────

  Future<void> sendOtp({required String phone}) async {
    final result = await HttpService.instance.post(
      ApiConfig.sendOtp,
      body: {'phone': phone},
    );
    if (result.success) {
      final devOtp = result.data?['otp']?.toString();
      if (devOtp != null) state = state.copyWith(devOtp: devOtp);
    } else {
      throw Exception(result.message.isNotEmpty ? result.message : 'Failed to send OTP');
    }
  }

  Future<void> verifyOtp({required String phone, required String otp}) async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.post(
      ApiConfig.verifyOtp,
      body: {'phone': phone, 'otp': otp},
    );
    if (result.success && result.data != null) {
      await _saveSession(result.data!);
      state = state.copyWith(devOtp: null);
    } else {
      state = state.copyWith(isLoading: false, error: result.message);
      throw Exception(result.message.isNotEmpty ? result.message : 'Invalid OTP');
    }
  }

  // ── Profile ───────────────────────────────────────────────────────────────

  Future<void> refreshProfile() async {
    final result = await HttpService.instance.get(ApiConfig.profile);
    if (result.success && result.data != null) {
      final userData = result.data!['data'] as Map<String, dynamic>? ?? {};
      await StorageService.saveUser(userData);
      state = state.copyWith(user: UserModel.fromJson(userData));
    }
  }

  // ── Session ───────────────────────────────────────────────────────────────

  Future<void> loadSavedUser() async {
    final userData = await StorageService.getUser();
    if (userData != null) state = state.copyWith(user: UserModel.fromJson(userData));
  }

  Future<void> logout() async {
    await HttpService.instance.post(ApiConfig.logout);
    await StorageService.clearAll();
    state = const AuthState();
  }

  Future<void> _saveSession(Map<String, dynamic> data) async {
    final token    = data['token']?.toString() ?? '';
    final userData = data['user'] as Map<String, dynamic>? ?? {};
    await StorageService.saveToken(token);
    await StorageService.saveUser(userData);
    state = state.copyWith(user: UserModel.fromJson(userData), isLoading: false);
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>(
  (ref) => AuthNotifier(),
);

final currentUserProvider = Provider<UserModel?>(
  (ref) => ref.watch(authProvider).user,
);
