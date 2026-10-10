import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/http_service.dart';
import '../../../core/config/api_config.dart';
import '../../../core/utils/storage_service.dart';
import '../models/otp_account_choice.dart';
import '../models/user_model.dart';

class AuthState {
  final UserModel? user;
  final bool isLoading;
  final String? error;
  final String? devOtp;

  /// Set when a verified OTP's number is saved on more than one account:
  /// nobody is signed in until the user picks one ([AuthNotifier.selectOtpAccount]).
  final OtpAccountChoice? accountChoice;

  const AuthState({this.user, this.isLoading = false, this.error, this.devOtp, this.accountChoice});

  /// [clearDevOtp] drops a dev-server OTP left from an earlier send -
  /// `devOtp: null` alone cannot, because null means "keep". Same for
  /// [clearAccountChoice].
  AuthState copyWith({
    UserModel? user,
    bool? isLoading,
    String? error,
    String? devOtp,
    bool clearDevOtp = false,
    OtpAccountChoice? accountChoice,
    bool clearAccountChoice = false,
  }) =>
      AuthState(
        user:          user      ?? this.user,
        isLoading:     isLoading ?? this.isLoading,
        error:         error,
        devOtp:        clearDevOtp ? null : (devOtp ?? this.devOtp),
        accountChoice: clearAccountChoice ? null : (accountChoice ?? this.accountChoice),
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
      // This build can show an account chooser, so a number saved on more
      // than one account still gets a code (see verifyOtp).
      body: {'phone': phone, 'can_choose_account': true},
    );
    if (result.success) {
      // Only a development server returns the code; never keep a stale one.
      final devOtp = result.data?['otp']?.toString();
      state = devOtp != null ? state.copyWith(devOtp: devOtp) : state.copyWith(clearDevOtp: true);
    } else {
      state = state.copyWith(clearDevOtp: true);
      throw Exception(result.message.isNotEmpty ? result.message : 'Failed to send OTP');
    }
  }

  /// Signs in, or - when the number is saved on more than one account -
  /// leaves [AuthState.accountChoice] set and signs nobody in yet.
  Future<void> verifyOtp({required String phone, required String otp}) async {
    state = state.copyWith(isLoading: true, error: null, clearAccountChoice: true);
    final result = await HttpService.instance.post(
      ApiConfig.verifyOtp,
      body: {'phone': phone, 'otp': otp, 'can_choose_account': true},
    );
    if (result.success && result.data != null) {
      final choice = OtpAccountChoice.fromVerifyResponse(result.data);
      if (choice != null) {
        if (choice.selectionToken.isEmpty || choice.accounts.isEmpty) {
          state = state.copyWith(isLoading: false);
          throw Exception('Could not load your accounts. Please request a new OTP.');
        }
        state = state.copyWith(isLoading: false, accountChoice: choice);
        return;
      }
      await _saveSession(result.data!);
      state = state.copyWith(clearDevOtp: true);
    } else {
      state = state.copyWith(isLoading: false, error: result.message);
      throw Exception(result.message.isNotEmpty ? result.message : 'Invalid OTP');
    }
  }

  /// After [verifyOtp] left an [AuthState.accountChoice]: sign in to [userId].
  Future<void> selectOtpAccount({required int userId}) async {
    final choice = state.accountChoice;
    if (choice == null) {
      throw Exception('This sign-in has expired. Please request a new OTP.');
    }
    final result = await HttpService.instance.post(
      ApiConfig.selectAccount,
      body: {'selection_token': choice.selectionToken, 'user_id': userId},
    );
    if (result.success && result.data?['token'] != null) {
      await _saveSession(result.data!);
      state = state.copyWith(clearDevOtp: true, clearAccountChoice: true);
      return;
    }
    // A request the server answered spends the token (single use), so the
    // choice is gone. A request that never arrived (no status) can retry.
    if (result.statusCode != 0) state = state.copyWith(clearAccountChoice: true);
    throw Exception(result.message.isNotEmpty ? result.message : 'This sign-in has expired. Please request a new OTP.');
  }

  // ── Forgot / Reset Password ───────────────────────────────────────────────

  /// Step 1: request an OTP to the account's registered mobile number.
  Future<void> forgotPassword({required String email}) async {
    final result = await HttpService.instance.post(
      ApiConfig.forgotPassword,
      body: {'email': email},
    );
    if (result.success) {
      final devOtp = result.data?['otp']?.toString();
      if (devOtp != null) state = state.copyWith(devOtp: devOtp);
    } else {
      throw Exception(result.message.isNotEmpty ? result.message : 'Could not send OTP');
    }
  }

  /// Step 2: verify OTP and set the new password. Logs the user in on success.
  Future<void> resetPassword({
    required String email,
    required String otp,
    required String password,
  }) async {
    state = state.copyWith(isLoading: true, error: null);
    final result = await HttpService.instance.post(
      ApiConfig.resetPassword,
      body: {
        'email':                 email,
        'otp':                   otp,
        'password':              password,
        'password_confirmation': password,
      },
    );
    if (result.success && result.data != null) {
      await _saveSession(result.data!);
      state = state.copyWith(clearDevOtp: true);
    } else {
      state = state.copyWith(isLoading: false, error: result.message);
      throw Exception(result.message.isNotEmpty ? result.message : 'Password reset failed');
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

  /// Local reset only — used when the backend already rejected the token
  /// (401). No logout API call: the token is dead.
  Future<void> clearSession() async {
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
